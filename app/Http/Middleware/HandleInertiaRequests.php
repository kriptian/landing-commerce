<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
        return array_merge(parent::share($request), [
            // ... otras cosas que ya tengas aquí ...
            'auth' => [
                'user' => $request->user(),
                // ¡Añadimos esta lógica!
                'can' => $request->user() ? $request->user()->getPermissionsViaRoles()->pluck('name') : [],
            ],
            'cart' => [
                'count' => $request->user() ? $request->user()->cart()->sum('quantity') : 0,
            ],
            

        ]);
    }
}