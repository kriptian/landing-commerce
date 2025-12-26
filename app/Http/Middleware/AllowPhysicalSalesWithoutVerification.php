<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowPhysicalSalesWithoutVerification
{
    /**
     * Handle an incoming request.
     * 
     * Permite que usuarios autenticados accedan a physical-sales y reportes sin verificación de email.
     * Esto es razonable porque:
     * 1. Si el usuario ya está autenticado, ya pasó la validación de credenciales
     * 2. Los permisos y planes se validan en otros middlewares
     * 3. La verificación de email no es crítica para funcionalidades operativas básicas
     * 4. Mejora la experiencia del usuario al no bloquear funcionalidades esenciales
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario está autenticado, permitir acceso sin verificación de email
        // La verificación de email se puede hacer después, pero no debe bloquear funcionalidades operativas
        if ($user) {
            return $next($request);
        }

        // Si no está autenticado, el middleware 'auth' se encargará de redirigir
        return $next($request);
    }
}

