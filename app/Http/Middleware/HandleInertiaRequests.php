<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Auth;
use App\Models\Store;
use App\Models\Product;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Incluir el token CSRF en las props compartidas para que Inertia lo tenga disponible
        // El token se actualiza automáticamente en el meta tag por Laravel en cada respuesta
        $csrfToken = csrf_token();
        
        return array_merge(parent::share($request), [
            'csrf_token' => $csrfToken,
            'auth' => [
                // IMPORTANTE: Solo usar el guard 'web' explícitamente para evitar mezclar con customers
                'user' => Auth::guard('web')->user(),
                // Enviamos un arreglo plano de permisos (directos y vía roles)
                // Solo para usuarios del guard 'web' (admin), no para customers
                'permissions' => (function () use ($request) {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (!$user || !method_exists($user, 'getAllPermissions')) {
                        return [];
                    }
                    return $user->getAllPermissions()->pluck('name')->values()->toArray();
                })(),
                // Opcional: roles por nombre para debug UI si se requiere
                'roles' => (function () use ($request) {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (!$user || !method_exists($user, 'roles')) {
                        return [];
                    }
                    return $user->roles->pluck('name')->values()->toArray();
                })(),
                // Flag explícito para súper admin real (por lista de correos)
                'isSuperAdmin' => (function () use ($request) {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (! $user) return false;
                    $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
                    $list = (array) config('app.super_admin_emails', []);
                    $allowed = collect([$single])->filter()->merge($list)->map(fn($e) => strtolower(trim($e)))->unique()->all();
                    return in_array(strtolower($user->email), $allowed, true);
                })(),
            ],
            'customer' => [
                'user' => $request->user('customer'),
                'defaultAddress' => (function () use ($request) {
                    $customer = $request->user('customer');
                    if (!$customer) {
                        return null;
                    }
                    // Cargar la relación defaultAddress
                    $defaultAddress = $customer->addresses()->where('is_default', true)->first();
                    if (!$defaultAddress) {
                        return null;
                    }
                    return [
                        'id' => $defaultAddress->id,
                        'label' => $defaultAddress->label,
                        'address_line_1' => $defaultAddress->address_line_1,
                        'address_line_2' => $defaultAddress->address_line_2,
                        'city' => $defaultAddress->city,
                        'state' => $defaultAddress->state,
                        'postal_code' => $defaultAddress->postal_code,
                        'country' => $defaultAddress->country,
                        'is_default' => $defaultAddress->is_default,
                    ];
                })(),
                'notifications' => (function () use ($request) {
                    $customer = $request->user('customer');
                    if (!$customer) {
                        return [];
                    }
                    return $customer->notifications()
                        ->where('is_read', false)
                        ->orderBy('created_at', 'desc')
                        ->limit(10)
                        ->get()
                        ->map(function ($notification) {
                            return [
                                'id' => $notification->id,
                                'order_id' => $notification->order_id,
                                'type' => $notification->type,
                                'title' => $notification->title,
                                'message' => $notification->message,
                                'created_at' => $notification->created_at->toISOString(),
                            ];
                        })
                        ->toArray();
                })(),
                'notificationsCount' => (function () use ($request) {
                    $customer = $request->user('customer');
                    if (!$customer) {
                        return 0;
                    }
                    return $customer->notifications()->where('is_read', false)->count();
                })(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'store_created' => fn () => $request->session()->get('store_created'),
            ],
            'cart' => [
                'count' => (function () use ($request) {
                    // Determinar la tienda en contexto (rutas públicas usan {store})
                    $storeParam = $request->route('store');
                    $storeId = null;
                    if ($storeParam instanceof Store) {
                        $storeId = $storeParam->id;
                    } elseif (is_string($storeParam) || is_numeric($storeParam)) {
                        $storeId = Store::where('slug', $storeParam)->value('id');
                    }

                    if (!$storeId) {
                        return 0; // fuera del catálogo público no mostramos conteo global
                    }

                    // Solo contar carrito para usuarios del guard 'web' (admin), no para customers
                    if (Auth::guard('web')->check()) {
                        $user = Auth::guard('web')->user();
                        if ($user && method_exists($user, 'cart')) {
                            // Contar únicamente items del carrito pertenecientes a esta tienda
                            return $user->cart()
                                ->whereRelation('product', 'store_id', $storeId)
                                ->sum('quantity');
                        }
                    }

                    // Invitado: filtrar por productos de la tienda actual
                    $sessionCart = $request->session()->get('guest_cart', []);
                    if (empty($sessionCart)) return 0;
                    // Si el item ya trae store_id, lo usamos directo; si no, hacemos fallback a mapear por producto
                    $needsLookup = collect($sessionCart)->contains(function ($row) { return !isset($row['store_id']); });
                    $idToStore = [];
                    if ($needsLookup) {
                        $productIds = collect($sessionCart)->pluck('product_id')->filter()->unique()->values();
                        if ($productIds->isNotEmpty()) {
                            $idToStore = Product::whereIn('id', $productIds)->pluck('store_id', 'id');
                        }
                    }
                    return collect($sessionCart)->sum(function ($row) use ($idToStore, $storeId) {
                        $rowStoreId = isset($row['store_id']) ? (int) $row['store_id'] : (int) ($idToStore[(int)($row['product_id'] ?? 0)] ?? 0);
                        if ($rowStoreId !== (int) $storeId) return 0;
                        return (int) ($row['quantity'] ?? 0);
                    });
                })(),
            ],
            
            // ===== AQUÍ VA LA MAGIA NUEVA =====
            'adminNotifications' => [
                'newOrdersCount' => Auth::check() && $request->user()->store 
                    ? $request->user()->store->orders()->where('status', 'recibido')->count() 
                    : 0,
            ],
            // ===================================
        ]);
    }
}