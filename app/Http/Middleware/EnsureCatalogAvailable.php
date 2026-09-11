<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureCatalogAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->route('store');
        if (! $store instanceof Store || ! $store->isCatalogPaused()) {
            return $next($request);
        }

        if (! $request->isMethod('GET')) {
            return back()->withErrors([
                'catalog' => 'El catalogo no esta disponible temporalmente. Intenta nuevamente mas tarde.',
            ]);
        }

        return Inertia::render('Public/CatalogUnavailable', [
            'store' => [
                'name' => $store->name,
                'logo_url' => $store->logo_url,
            ],
        ])->toResponse($request)->setStatusCode(423);
    }
}
