<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use App\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:gestionar usuarios');
    }

    public function index(Request $request)
    {
        $store = $request->user()->store;
        
        return Inertia::render('Users/Index', [
            'users' => $store->users()->with('roles')->get(),
            'roles' => $store->roles()->with('permissions')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            // AJUSTE: Buscamos solo los roles de la tienda actual
            'roles' => auth()->user()->store->roles()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $store = $request->user()->store;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Email único solo para esta tienda
                Rule::unique('users')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                }),
            ],
            'password' => 'required|string|min:8|confirmed',
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id)
                                 ->where('guard_name', config('auth.defaults.guard', 'web'));
                }),
            ],
        ]);
        
        // Limitar por cupo de usuarios de la tienda
        $currentUsers = $store->users()->count();
        $maxUsers = $store->max_users ?? 0;
        if ($maxUsers > 0 && $currentUsers >= $maxUsers) {
            return back()->withErrors(['limit' => 'Has alcanzado el límite de usuarios permitidos para tu plan.']);
        }

        try {
            DB::beginTransaction();

            $user = $store->users()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(), // Marcar como verificado para permitir acceso
            ]);
            
            // Buscar el rol específico de la tienda (importante: debe pertenecer a esta tienda)
            $role = Role::where('name', $validated['role'])
                ->where('store_id', $store->id)
                ->where('guard_name', config('auth.defaults.guard', 'web'))
                ->first();
            
            if (!$role) {
                DB::rollBack();
                return back()->withErrors(['role' => 'El rol seleccionado no existe para esta tienda.']);
            }
            
            // Asignar el rol por instancia para evitar colisiones
            $user->assignRole($role);

            DB::commit();

            // Regenerar el token CSRF después de crear el usuario para evitar errores 419
            $request->session()->regenerateToken();

            return redirect()->route('admin.users.index')->with('success', '¡Usuario creado con éxito!');
        } catch (\Exception $e) {
            DB::rollBack();
            // Log del error para debugging
            \Log::error('Error al crear usuario: ' . $e->getMessage(), [
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
                : 'Hubo un error al crear el usuario. Por favor, intenta nuevamente.';
            
            // Mensajes más específicos según el tipo de error
            if (str_contains($e->getMessage(), 'SQLSTATE')) {
                $errorMessage = config('app.debug')
                    ? 'Error de base de datos: ' . $e->getMessage()
                    : 'Error de base de datos. Verifica que los datos sean correctos.';
            } elseif (str_contains($e->getMessage(), 'Role') || str_contains($e->getMessage(), 'role')) {
                $errorMessage = config('app.debug')
                    ? 'Error al asignar rol: ' . $e->getMessage()
                    : 'Error al asignar rol. Contacta al soporte.';
            }
            
            return back()->withErrors(['error' => $errorMessage]);
        }
    }

    public function edit(User $user)
    {
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        return Inertia::render('Users/Edit', [
            'user' => $user->load('roles'),
            // AJUSTE: Hacemos lo mismo aquí
            'roles' => auth()->user()->store->roles()->pluck('name'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        if ($user->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        $store = $request->user()->store;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                // Email único solo para esta tienda, ignorando el usuario actual
                Rule::unique('users')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                })->ignore($user->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where(function ($query) use ($store) {
                    return $query->where('store_id', $store->id)
                                 ->where('guard_name', config('auth.defaults.guard', 'web'));
                }),
            ],
        ]);

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        try {
            DB::beginTransaction();

            $user->update($userData);
            
            // Buscar el rol específico de la tienda
            $role = Role::where('name', $validated['role'])
                ->where('store_id', $store->id)
                ->where('guard_name', config('auth.defaults.guard', 'web'))
                ->first();
            
            if (!$role) {
                DB::rollBack();
                return back()->withErrors(['role' => 'El rol seleccionado no existe para esta tienda.']);
            }
            
            // Sincronizar roles (eliminar todos y asignar solo el nuevo)
            $user->syncRoles([$role]);

            DB::commit();

            // Regenerar el token CSRF después de actualizar el usuario para evitar errores 419
            $request->session()->regenerateToken();

            return redirect()->route('admin.users.index');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al actualizar usuario: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);
            $request->session()->regenerateToken();
            // En desarrollo, mostrar el error real; en producción, mensaje genérico
            $errorMessage = config('app.debug') 
                ? 'Error: ' . $e->getMessage() . ' (Línea: ' . $e->getLine() . ')'
                : 'Hubo un error al actualizar el usuario. Por favor, intenta nuevamente.';
            
            // Mensajes más específicos según el tipo de error
            if (str_contains($e->getMessage(), 'SQLSTATE')) {
                $errorMessage = config('app.debug')
                    ? 'Error de base de datos: ' . $e->getMessage()
                    : 'Error de base de datos. Verifica que los datos sean correctos.';
            } elseif (str_contains($e->getMessage(), 'Role') || str_contains($e->getMessage(), 'role')) {
                $errorMessage = config('app.debug')
                    ? 'Error al asignar rol: ' . $e->getMessage()
                    : 'Error al asignar rol. Contacta al soporte.';
            }
            
            return back()->withErrors(['error' => $errorMessage]);
        }
    }

    public function destroy(User $user, Request $request)
    {
        // Regla de seguridad: No podés eliminarte a vos mismo
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'No puedes eliminar tu propio usuario.']);
        }
        // Regla de seguridad: Solo podés borrar usuarios de tu propia tienda
        if ($user->store_id !== $request->user()->store_id) {
            abort(403);
        }

        $user->delete();
        
        // Regenerar el token CSRF después de eliminar el usuario para evitar errores 419
        $request->session()->regenerateToken();
        
        return back();
    }
}