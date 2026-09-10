<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            if ($user->is_admin) {
                return true;
            }
            // Los administradores de tienda tienen acceso total a las áreas del panel
            if (method_exists($user, 'hasRole') && $user->hasRole('Administrador')) {
                return true;
            }
        });
    }
}
