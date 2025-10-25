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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\StoreCredentialsMail;
use App\Notifications\NewStoreCreated;

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
            // Campos opcionales para la auto-configuración
            'phone' => ['nullable', 'string', 'max:20'],
            'plan' => ['nullable', 'in:emprendedor,negociante'],
            'plan_cycle' => ['nullable', 'in:mensual,anual'],
            'max_users' => ['nullable', 'integer', 'min:1'],
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
            'phone' => $request->input('phone'),
            'plan' => $request->input('plan', 'emprendedor'),
            'plan_cycle' => $request->input('plan_cycle', 'mensual'),
            'max_users' => $request->input('max_users', 3),
            'plan_started_at' => now(),
        ]);
        
        // 3. Le asignamos el ID de la tienda al usuario y guardamos
        $user->store_id = $store->id;
        $user->save();
        
        // Le asignamos el rol por defecto
        $user->assignRole('Administrador');

        event(new Registered($user));

        // En lugar de iniciar sesión automáticamente, enviamos correo y devolvemos flash para modal

        // Notificar al súper admin y vía WhatsApp opcional
        try {
            $single = (string) config('app.super_admin_email', env('SUPER_ADMIN_EMAIL'));
            $list = (array) config('app.super_admin_emails', []);
            $emails = collect([$single])->filter()->merge($list)->map(fn($e) => strtolower(trim($e)))->unique();
            if ($emails->isNotEmpty()) {
                $admins = \App\Models\User::whereIn(\DB::raw('LOWER(email)'), $emails->all())->get();
                foreach ($admins as $admin) {
                    $admin->notify(new NewStoreCreated($store));
                }
            }
        } catch (\Throwable $e) {
            // Intencionalmente silencioso para no bloquear el alta
        }

        // WhatsApp: abrir intent si está configurado (en background para el usuario)
        try {
            $phone = config('services.whatsapp.admin_phone');
            if ($phone) {
                $text = "Nueva tienda creada: {$store->name} (ID {$store->id}). Contactar al cliente para onboarding.";
                $query = http_build_query(['text' => $text], '', '&', PHP_QUERY_RFC3986);
                // Guardamos en sesión para que el frontend pueda opcionalmente redirigir si se desea
                session()->flash('whatsapp_new_store_url', 'https://wa.me/' . preg_replace('/[^0-9]/', '', $phone) . '?' . $query);
            }
        } catch (\Throwable $e) {}

        // Enviar credenciales al email del dueño
        try {
            Mail::to($user->email)->send(new StoreCredentialsMail(
                storeName: $store->name,
                email: $user->email,
                plainPassword: $request->password,
            ));
        } catch (\Throwable $e) {}

        // Guardamos un flash que el Front-end leerá para mostrar modal de éxito
        return redirect()->back()->with('store_created', [
            'email' => $user->email,
            'store_name' => $store->name,
        ]);
    }
}