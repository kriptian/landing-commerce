<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Store;
use App\Models\Role;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use App\Mail\StoreCredentialsMail;
use App\Notifications\NewStoreCreated;
use Spatie\Permission\Models\Permission;

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

        // Usar transacción para asegurar consistencia
        try {
            DB::beginTransaction();

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
            
            // 3. Le asignamos el ID de la tienda al usuario y marcamos como verificado
            $user->store_id = $store->id;
            $user->email_verified_at = now(); // Marcar como verificado para permitir login
            $user->save();
            
            // 4. Crear/obtener rol Administrador de la tienda y asignarle todos los permisos
            $adminRole = Role::firstOrCreate([
                'name' => 'Administrador',
                'store_id' => $store->id,
                'guard_name' => config('auth.defaults.guard', 'web'),
            ]);
            
            // Sincronizar todos los permisos al rol Administrador (si existen)
            // Nota: Los permisos son globales (sin store_id), así que los asignamos directamente
            try {
                $permissions = Permission::all();
                if ($permissions->isNotEmpty()) {
                    $adminRole->syncPermissions($permissions);
                }
            } catch (\Exception $e) {
                // Si hay error con permisos, loguear pero continuar (el rol se crea igual)
                \Log::warning('No se pudieron asignar permisos al rol Administrador: ' . $e->getMessage());
            }
            
            // Limpiar caché de permisos
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            
            // 5. Asignar el rol Administrador al usuario (por instancia para evitar colisiones)
            $user->assignRole($adminRole);

            DB::commit();

            event(new Registered($user));
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            // Si es un error de validación, re-lanzarlo para que Inertia lo maneje correctamente
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            // Log del error para debugging
            \Log::error('Error al crear tienda: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'password_confirmation']),
            ]);
            // Regenerar token CSRF antes de redirigir con error
            $request->session()->regenerateToken();
            // En desarrollo, mostrar el error real; en producción, mensaje genérico
            $errorMessage = config('app.debug') 
                ? 'Error: ' . $e->getMessage() . ' (Línea: ' . $e->getLine() . ')'
                : 'Hubo un error al crear la tienda. Por favor, intenta nuevamente.';
            
            // Mensajes más específicos según el tipo de error
            if (str_contains($e->getMessage(), 'SQLSTATE')) {
                $errorMessage = config('app.debug')
                    ? 'Error de base de datos: ' . $e->getMessage()
                    : 'Error de base de datos. Verifica que los datos sean correctos.';
            } elseif (str_contains($e->getMessage(), 'Permission') || str_contains($e->getMessage(), 'permission')) {
                $errorMessage = config('app.debug')
                    ? 'Error al asignar permisos: ' . $e->getMessage()
                    : 'Error al asignar permisos. Contacta al soporte.';
            } elseif (str_contains($e->getMessage(), 'Role') || str_contains($e->getMessage(), 'role')) {
                $errorMessage = config('app.debug')
                    ? 'Error al crear rol: ' . $e->getMessage()
                    : 'Error al crear rol. Contacta al soporte.';
            }
            
            return back()->withErrors(['error' => $errorMessage]);
        }

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

        // Regenerar token CSRF después de crear la tienda
        $request->session()->regenerateToken();

        // Guardamos un flash que el Front-end leerá para mostrar modal de éxito
        return redirect()->back()->with('store_created', [
            'email' => $user->email,
            'store_name' => $store->name,
        ]);
    }
}