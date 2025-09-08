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
        // Si el usuario que hace la petición no está logueado O no es admin,
        // le negamos el acceso con un error 403.
        if (! $request->user() || ! $request->user()->is_admin) {
            abort(403, 'Acción no autorizada.');
        }

        // Si es admin, lo dejamos seguir.
        return $next($request);
    }
}