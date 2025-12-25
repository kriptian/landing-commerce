<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class CustomerAuthController extends Controller
{
    /**
     * Mostrar formulario de registro de cliente
     */
    public function showRegisterForm(Store $store): Response
    {
        return Inertia::render('Customer/Register', [
            'store' => $store,
        ]);
    }

    /**
     * Registrar nuevo cliente
     */
    public function register(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                // Email único solo para esta tienda en customers
                'unique:customers,email,NULL,id,store_id,' . $store->id,
                // IMPORTANTE: No permitir emails que ya existen en la tabla de usuarios (admin)
                function ($attribute, $value, $fail) {
                    if (\App\Models\User::where('email', $value)->exists()) {
                        $fail('Este correo electrónico ya está registrado como administrador. Por favor, usa otro correo.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = Customer::create([
            'store_id' => $store->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        Auth::guard('customer')->login($customer);

        return redirect()->route('catalogo.index', ['store' => $store->slug])
            ->with('success', '¡Cuenta creada exitosamente!');
    }

    /**
     * Mostrar formulario de login de cliente
     */
    public function showLoginForm(Store $store): Response
    {
        return Inertia::render('Customer/Login', [
            'store' => $store,
        ]);
    }

    /**
     * Autenticar cliente
     */
    public function login(Request $request, Store $store)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Asegurar que el cliente pertenece a esta tienda
        $customer = Customer::where('store_id', $store->id)
            ->where('email', $credentials['email'])
            ->first();

        if ($customer && Hash::check($credentials['password'], $customer->password)) {
            Auth::guard('customer')->login($customer);
            $request->session()->regenerate();

            return redirect()->intended(route('catalogo.index', ['store' => $store->slug]))
                ->with('success', '¡Bienvenido de nuevo!');
        }

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('email');
    }

    /**
     * Cerrar sesión del cliente
     */
    public function logout(Request $request, Store $store)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('catalogo.index', ['store' => $store->slug])
            ->with('success', 'Sesión cerrada exitosamente');
    }
}

