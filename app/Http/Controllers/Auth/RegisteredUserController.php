<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;

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
        // ===== ESTA ES LA VALIDACIÓN QUE CAMBIA =====
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Le añadimos la regla 'unique' para el nombre de la tienda
            'store_name' => ['required', 'string', 'max:255', 'unique:'.Store::class.',name'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        // ===========================================

        // 1. Creamos el usuario
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 2. Creamos la tienda usando el 'store_name' que vino del formulario
        $store = Store::create([
            'name' => $request->store_name, // <-- Usamos el nombre del formulario
            'user_id' => $user->id,
        ]);
        
        // 3. Le asignamos el ID de la tienda al usuario y guardamos
        $user->store_id = $store->id;
        $user->save();
        
        // Le asignamos el rol por defecto
        $user->assignRole('Administrador');

        event(new Registered($user));

        Auth::login($user);

        // Lo mandamos a la página para que termine de configurar su tienda
        return redirect()->route('store.setup');
    }
}