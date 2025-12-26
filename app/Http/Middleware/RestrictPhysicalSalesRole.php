<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPhysicalSalesRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si el usuario tiene el rol "physical-sales", solo puede acceder a physical-sales
        if ($user && $user->hasRole('physical-sales')) {
            // Permitir acceso a physical-sales y sus rutas relacionadas
            $allowedRoutes = [
                'admin.physical-sales.index',
                'admin.physical-sales.search-products',
                'admin.physical-sales.get-product-by-barcode',
                'admin.physical-sales.store',
                'admin.physical-sales.open-drawer',
                'admin.physical-sales.show',
                'admin.physical-sales.export',
                'logout',
            ];

            $currentRoute = $request->route()->getName();

            // Si no está en una ruta permitida, redirigir a physical-sales
            if (!in_array($currentRoute, $allowedRoutes)) {
                return redirect()->route('admin.physical-sales.index');
            }
        }

        return $next($request);
    }
}

