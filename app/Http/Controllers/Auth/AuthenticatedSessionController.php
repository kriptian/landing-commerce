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

        $request->session()->regenerate();

        // Detectar si es el primer login del usuario
        $user = Auth::user();
        
        // Verificar si debe mostrar el tour
        $showTour = false;
        $toursToShow = [];
        
        if ($user) {
            $neverShowTours = $user->never_show_tours ?? [];
            $completedTours = $user->completed_tours ?? [];
            
            // Si el usuario seleccionó "No volver a mostrar" para el tour principal, NO mostrar NINGÚN tour
            if (in_array('main', $neverShowTours)) {
                $showTour = false;
            } else {
                // Si el tour principal ya está completado, NO mostrar
                if (in_array('main', $completedTours)) {
                    $showTour = false;
                } else {
                    // Si es el primer login, mostrar tour
                    if ($user->first_login ?? true) {
                        $showTour = true;
                    } else {
                        // Si no es primer login, verificar si hay tours programados para recordar
                        $remindLaterTours = $user->remind_later_tours ?? [];
                        if (!empty($remindLaterTours)) {
                            $showTour = true;
                        }
                    }
                }
            }
        }

        return redirect()->intended(route('dashboard', [
            'show_tour' => (bool) $showTour,
            'tours_to_show' => $toursToShow
        ]));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // return redirect('/'); si lo hago así me muestra el listado de todas las tiendas que son mis clientes, por eso lo dejo asi
        return redirect('/login');
    }
}
