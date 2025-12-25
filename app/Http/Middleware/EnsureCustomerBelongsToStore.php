<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerBelongsToStore
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user('customer');
        $store = $request->route('store');

        if ($customer && $store && $customer->store_id !== $store->id) {
            abort(403, 'No tienes acceso a esta tienda');
        }

        return $next($request);
    }
}

