<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\Store;
use App\Services\DeploymentAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

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
        $storeId = $this->storeIdFromRoute($request);
        $customer = $request->user('customer');
        $user = Auth::guard('web')->user();
        $capabilities = $this->adminCapabilities($request);

        if (! $storeId || (int) $customer?->store_id !== $storeId) {
            $customer = null;
        }

        return array_merge(parent::share($request), [
            'csrf_token' => $csrfToken,
            'auth' => [
                // IMPORTANTE: Solo usar el guard 'web' explícitamente para evitar mezclar con customers
                'user' => $user,
                'store' => $user?->store,
                // Enviamos un arreglo plano de permisos (directos y vía roles)
                // Solo para usuarios del guard 'web' (admin), no para customers
                'permissions' => (function () {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (! $user || ! method_exists($user, 'getAllPermissions')) {
                        return [];
                    }

                    return $user->getAllPermissions()->pluck('name')->values()->toArray();
                })(),
                // Opcional: roles por nombre para debug UI si se requiere
                'roles' => (function () {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (! $user || ! method_exists($user, 'roles')) {
                        return [];
                    }

                    return $user->roles->pluck('name')->values()->toArray();
                })(),
                // Flag explícito para súper admin real (por lista de correos)
                'isSuperAdmin' => (function () {
                    $user = Auth::guard('web')->user(); // Guard 'web' explícito
                    if (! $user) {
                        return false;
                    }
                    $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
                    $list = (array) config('app.super_admin_emails', []);
                    $allowed = collect([$single])->filter()->merge($list)->map(fn ($e) => strtolower(trim($e)))->unique()->all();

                    return in_array(strtolower($user->email), $allowed, true);
                })(),
                'canDeploy' => app(DeploymentAuthorizer::class)->allows(Auth::guard('web')->user(), $request),
                'capabilities' => $capabilities,
            ],
            'customer' => [
                'user' => $customer,
                'defaultAddress' => (function () use ($customer) {
                    if (! $customer) {
                        return null;
                    }
                    // Cargar la relación defaultAddress
                    $defaultAddress = $customer->addresses()->where('is_default', true)->first();
                    if (! $defaultAddress) {
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
                'notifications' => (function () use ($customer) {
                    if (! $customer) {
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
                'notificationsCount' => (function () use ($customer) {
                    if (! $customer) {
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

                    if (! $storeId) {
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
                    if (empty($sessionCart)) {
                        return 0;
                    }
                    // Si el item ya trae store_id, lo usamos directo; si no, hacemos fallback a mapear por producto
                    $needsLookup = collect($sessionCart)->contains(function ($row) {
                        return ! isset($row['store_id']);
                    });
                    $idToStore = [];
                    if ($needsLookup) {
                        $productIds = collect($sessionCart)->pluck('product_id')->filter()->unique()->values();
                        if ($productIds->isNotEmpty()) {
                            $idToStore = Product::whereIn('id', $productIds)->pluck('store_id', 'id');
                        }
                    }

                    return collect($sessionCart)->sum(function ($row) use ($idToStore, $storeId) {
                        $rowStoreId = isset($row['store_id']) ? (int) $row['store_id'] : (int) ($idToStore[(int) ($row['product_id'] ?? 0)] ?? 0);
                        if ($rowStoreId !== (int) $storeId) {
                            return 0;
                        }

                        return (int) ($row['quantity'] ?? 0);
                    });
                })(),
            ],

            'adminNotifications' => [
                'newOrdersCount' => ($capabilities['orders'] ?? 'hidden') === 'enabled' && $user?->store
                    ? $user->store->orders()->where('status', 'recibido')->count()
                    : 0,
            ],
        ]);
    }

    /** @return array<string, string> */
    private function adminCapabilities(Request $request): array
    {
        $user = Auth::guard('web')->user();
        if (! $user) {
            return [];
        }

        $hidden = [
            'dashboard', 'physicalSales', 'orders', 'customers', 'coupons', 'products',
            'categories', 'inventory', 'catalogCustomization', 'gallery', 'pdfCatalogBuilder',
            'reports', 'users', 'superStores', 'deployments', 'profile',
        ];

        if ($user->hasRole('physical-sales')) {
            return array_replace(array_fill_keys($hidden, 'hidden'), ['physicalSales' => 'enabled']);
        }

        $plan = $user->store?->plan ?? 'emprendedor';
        $isPdfCreator = $plan === 'creador_pdf';
        $isSuperAdmin = $this->isSuperAdmin($user->email);
        $hasPlan = fn (array $plans): bool => $isSuperAdmin || in_array($plan, $plans, true);
        $state = function (array $plans, ?string $permission = null) use ($user, $hasPlan, $isSuperAdmin): string {
            if ($permission && ! $isSuperAdmin && ! $user->can($permission)) {
                return 'hidden';
            }

            return $hasPlan($plans) ? 'enabled' : 'locked';
        };

        return [
            'dashboard' => ! $isPdfCreator && $user->can('ver dashboard') ? 'enabled' : 'hidden',
            'physicalSales' => $isPdfCreator ? 'hidden' : $state(['emprendedor', 'negociante'], 'ver inventario'),
            'orders' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'ver ordenes'),
            'customers' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'ver clientes'),
            'coupons' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'gestionar cupones'),
            'products' => $isPdfCreator ? 'hidden' : $state(['emprendedor', 'negociante'], 'ver inventario'),
            'categories' => $isPdfCreator ? 'hidden' : $state(['emprendedor', 'negociante'], 'gestionar categorias'),
            'inventory' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'ver inventario'),
            'catalogCustomization' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'gestionar galeria'),
            'gallery' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'gestionar galeria'),
            'pdfCatalogBuilder' => $state(['negociante', 'creador_pdf']),
            'reports' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'ver reportes'),
            'users' => $isPdfCreator ? 'hidden' : $state(['negociante'], 'gestionar usuarios'),
            'superStores' => $isSuperAdmin ? 'enabled' : 'hidden',
            'deployments' => app(DeploymentAuthorizer::class)->allows($user, $request) ? 'enabled' : 'hidden',
            'profile' => 'enabled',
        ];
    }

    private function isSuperAdmin(string $email): bool
    {
        $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
        $list = (array) config('app.super_admin_emails', []);
        $allowed = collect([$single])
            ->filter()
            ->merge($list)
            ->map(fn ($value) => strtolower(trim($value)))
            ->unique()
            ->all();

        return in_array(strtolower($email), $allowed, true);
    }

    private function storeIdFromRoute(Request $request): ?int
    {
        $store = $request->route('store');

        if ($store instanceof Store) {
            return (int) $store->id;
        }

        if (is_string($store) || is_numeric($store)) {
            return Store::query()->where('slug', $store)->value('id');
        }

        return null;
    }
}
