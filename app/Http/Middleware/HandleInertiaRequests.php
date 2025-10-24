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
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user(),
                // Enviamos un arreglo plano de permisos (directos y vía roles)
                'permissions' => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->toArray()
                    : [],
                // Opcional: roles por nombre para debug UI si se requiere
                'roles' => $request->user()
                    ? $request->user()->roles->pluck('name')->values()->toArray()
                    : [],
                // Flag explícito para súper admin real (por email de entorno)
                'isSuperAdmin' => $request->user()
                    ? strcasecmp($request->user()->email, (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL')) ) === 0
                    : false,
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

                    if (Auth::check()) {
                        // Contar únicamente items del carrito pertenecientes a esta tienda
                        return Auth::user()->cart()
                            ->whereRelation('product', 'store_id', $storeId)
                            ->sum('quantity');
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