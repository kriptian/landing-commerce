<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store; // <-- Asegurate de tener este import
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
{
    $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
        'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ]);

    // 1. Creamos el usuario
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
    ]);

    // 2. Le creamos su tienda
    $store = Store::create([
        'name' => 'La Tienda de ' . $user->name,
        'user_id' => $user->id,
    ]);

    // 3. Le asignamos el ID de la tienda al usuario y guardamos
    $user->store_id = $store->id;
    $user->save();

    // 4. ¡LA PRUEBA DEFINITIVA!
    // Detenemos todo y le pedimos que nos muestre cómo quedó el usuario
    // recién guardado en la base de datos.
    // dd('Así quedó guardado el usuario en la BD:', $user->fresh()->toArray());


    // El código de abajo no se ejecutará por ahora
    $user->assignRole('Administrador');
    event(new Registered($user));
    Auth::login($user);
    return redirect()->route('store.setup');
}
}