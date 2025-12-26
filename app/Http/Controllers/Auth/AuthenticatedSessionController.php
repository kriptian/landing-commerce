<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Regenerar sesión ANTES de obtener el usuario para asegurar que el token CSRF se actualice
        $request->session()->regenerate();
        
        // Forzar la actualización del token CSRF en la respuesta
        $request->session()->regenerateToken();

        $user = Auth::user();

        // Si el usuario tiene el rol "physical-sales", redirigir directamente a VENDER!
        // Nota: El middleware AllowPhysicalSalesWithoutVerification permite el acceso sin verificación
        if ($user->hasRole('physical-sales')) {
            return redirect()->route('admin.physical-sales.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        // Regenerar token ANTES de redirigir para asegurar que el nuevo token esté disponible
        $request->session()->regenerateToken();

        // return redirect('/'); si lo hago así me muestra el listado de todas las tiendas que son mis clientes, por eso lo dejo asi
        return redirect('/login');
    }
}
