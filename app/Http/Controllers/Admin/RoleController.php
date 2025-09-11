<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role; // <-- Usamos nuestro modelo personalizado
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;

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
        
        // AJUSTE: Creamos el rol directamente asociado a la tienda.
        // Laravel se encarga de poner el store_id.
        $role = $store->roles()->create(['name' => $validated['name']]);
        
        $role->syncPermissions($validated['permissions']);

        return redirect()->route('admin.users.index')->with('message', 'Rol creado exitosamente.');
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
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => 'required|array',
        ]);

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        return redirect()->route('admin.users.index')->with('message', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role)
    {
        if ($role->store_id !== auth()->user()->store_id) {
            abort(403);
        }

        if (in_array($role->name, ['Administrador', 'Vendedor'])) {
            return back()->withErrors(['delete' => 'No se pueden eliminar los roles base.']);
        }
        
        $role->delete();

        return redirect()->route('admin.users.index')->with('message', 'Rol eliminado exitosamente.');
    }
}