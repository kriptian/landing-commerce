<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowPhysicalSalesWithoutVerification
{
    /**
     * Handle an incoming request.
     *
     * Permite omitir la verificación solo al rol operativo de ventas físicas.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->hasRole('physical-sales')) {
            return $next($request);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? abort(403, 'Tu correo electrónico no ha sido verificado.')
                : redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
