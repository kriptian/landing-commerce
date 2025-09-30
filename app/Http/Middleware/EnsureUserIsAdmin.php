<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Solo el súper admin real puede acceder (por email de entorno)
        $superEmail = config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
        if (! $user || strcasecmp($user->email, (string) $superEmail) !== 0) {
            abort(403, 'Acción no autorizada.');
        }

        // Si es admin, lo dejamos seguir.
        return $next($request);
    }
}