<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Auth;

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
            ],
            'cart' => [
                'count' => Auth::check() ? Auth::user()->cart()->sum('quantity') : 0,
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