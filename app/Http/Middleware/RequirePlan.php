<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlan
{
    public function handle(Request $request, Closure $next, string $plan): Response
    {
        $user = $request->user();
        $storePlan = $user?->store?->plan ?? 'emprendedor';

        // Súper admin siempre pasa
        $superEmail = config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
        if ($user && strcasecmp($user->email, (string) $superEmail) === 0) {
            return $next($request);
        }

        if ($storePlan !== $plan) {
            abort(403, 'Tu plan actual no permite acceder a esta sección.');
        }

        return $next($request);
    }
}


