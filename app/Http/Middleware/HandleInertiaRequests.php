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
                'can' => $request->user() ? $request->user()->getPermissionsViaRoles()->pluck('name') : [],
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