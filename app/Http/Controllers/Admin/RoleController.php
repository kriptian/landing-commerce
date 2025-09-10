<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    // No necesitamos el index aquí porque ya lo maneja el UserController

    public function create()
    {
        return Inertia::render('Roles/Create', [
            'permissions' => Permission::all()->pluck('name'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'required|array',
        ]);

        $role = Role::create(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        // AJUSTE: Redirigimos a la página de usuarios, que ahora contiene los roles
        return redirect()->route('admin.users.index')->with('message', 'Rol creado exitosamente.');
    }

    public function edit(Role $role)
    {
        // Pasamos el rol y todos los permisos a la vista de edición
        return Inertia::render('Roles/Edit', [
            'role' => $role->load('permissions'),
            'permissions' => Permission::all()->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => 'required|array',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return redirect()->route('admin.users.index')->with('message', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role)
    {
        // Regla de seguridad: No dejar borrar los roles principales
        if (in_array($role->name, ['Administrador', 'Vendedor'])) {
            return back()->withErrors(['delete' => 'No se pueden eliminar los roles base.']);
        }
        
        $role->delete();

        return redirect()->route('admin.users.index')->with('message', 'Rol eliminado exitosamente.');
    }
}