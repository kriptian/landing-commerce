<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role; // <-- Usamos nuestro modelo personalizado
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    // Esta función no la estamos usando en el menú, pero es bueno tenerla completa.
    public function index(Request $request)
    {
        return Inertia::render('Roles/Index', [
            'roles' => $request->user()->store->roles()->with('permissions')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'required|array',
        ]);

        $store = $request->user()->store;
        
        $role = $store->roles()->create([
            'name' => $validated['name'],
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);

        $role->syncPermissions($validated['permissions']);

        // Limpiamos la caché de permisos de Spatie para aplicar cambios de inmediato
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Redirigimos al listado (pestaña de Roles) con notificación de éxito
        return redirect()->route('admin.users.index', ['tab' => 'roles'])->with('success', '¡Rol creado con éxito!');
    }

    public function edit(Role $role)
    {
        // Medida de seguridad: Asegurarse de que el rol pertenezca a la tienda del usuario.
        if ($role->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($role->store_id !== auth()->user()->store_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('roles')->where(function ($q) use ($role) {
                    return $q->where('store_id', auth()->user()->store_id)
                             ->where('guard_name', $role->guard_name ?: config('auth.defaults.guard', 'web'));
                })->ignore($role->id)
            ],
            'permissions' => 'required|array',
        ]);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $role->guard_name ?: config('auth.defaults.guard', 'web'),
        ]);
        $role->syncPermissions($validated['permissions']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Redirigimos al editor del propio rol con flash
        return redirect()->route('admin.roles.edit', $role->id)->with('success', 'Rol actualizado');
    }

    public function destroy(Role $role, Request $request)
    {
        if ($role->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        // Única restricción: si hay usuarios con este rol, no permitimos eliminar
        if (method_exists($role, 'users') && $role->users()->exists()) {
            throw ValidationException::withMessages([
                'delete' => 'No se puede eliminar un rol que está asignado a usuarios.'
            ]);
        }
        
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // CAMBIO: Redirigimos al índice de usuarios, donde está la pestaña de roles.
        return redirect()->route('admin.users.index')->with('success', 'Rol eliminado');
    }
}