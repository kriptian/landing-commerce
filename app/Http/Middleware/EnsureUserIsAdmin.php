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
        // Soporta múltiples súper admins por email
        $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
        $list = (array) config('app.super_admin_emails', []);
        $allowed = collect([$single])->filter()->merge($list)->map(fn($e) => strtolower(trim($e)))->unique()->all();

        if (! $user || ! in_array(strtolower($user->email), $allowed, true)) {
            abort(403, 'Acción no autorizada.');
        }

        // Si es admin, lo dejamos seguir.
        return $next($request);
    }
}