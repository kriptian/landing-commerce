<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class StoreController extends Controller
{
    public function index()
    {
        return Inertia::render('Super/Stores/Index', [
            'stores' => Store::withCount('users')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Super/Stores/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:users,email',
            'owner_password' => 'required|string|min:8|confirmed',
            'max_users' => 'required|integer|min:1',
            'plan' => 'required|in:emprendedor,negociante',
            'plan_cycle' => 'nullable|in:mensual,anual',
        ]);

        // Crear usuario propietario (admin de la tienda)
        $owner = User::create([
            'name' => $validated['owner_name'],
            'email' => $validated['owner_email'],
            'password' => Hash::make($validated['owner_password']),
            'is_admin' => false,
        ]);

        // Crear la tienda y asociarla al propietario
        $store = Store::create([
            'name' => $validated['name'],
            'user_id' => $owner->id,
            'phone' => $validated['phone'],
            'max_users' => $validated['max_users'],
            'plan' => $validated['plan'],
            'plan_cycle' => $validated['plan_cycle'] ?? 'mensual',
            'plan_started_at' => now(),
        ]);

        // Asignar store_id al propietario
        $owner->store_id = $store->id;
        // Marcamos como verificado para permitir acceso a rutas 'verified'
        $owner->email_verified_at = now();
        $owner->save();

        // Crear/obtener rol Administrador de la tienda y asignarle todos los permisos
        $adminRole = Role::firstOrCreate([
            'name' => 'Administrador',
            'store_id' => $store->id,
            'guard_name' => config('auth.defaults.guard', 'web'),
        ]);
        $adminRole->syncPermissions(Permission::all());
        // Limpiamos la caché de permisos para asegurar que el frontend reciba permisos actualizados
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        // Asignamos el rol por instancia para evitar colisiones por nombre entre tiendas
        $owner->assignRole($adminRole);

        return redirect()->route('super.stores.index');
    }

    public function edit(Store $store)
    {
        return Inertia::render('Super/Stores/Edit', [
            'store' => $store->load('owner'),
        ]);
    }

    public function update(Request $request, Store $store)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'max_users' => 'required|integer|min:1',
            'phone' => 'required|string|max:20',
            // Promoción global ya no se edita aquí
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:users,email,' . $store->user_id,
            'owner_password' => 'nullable|string|min:8|confirmed',
            'plan' => 'required|in:emprendedor,negociante',
            'plan_cycle' => 'nullable|in:mensual,anual',
        ]);

        DB::transaction(function () use ($store, $validated) {
            $store->update([
                'name' => $validated['name'],
                'max_users' => $validated['max_users'],
                'phone' => $validated['phone'],
                'plan' => $validated['plan'],
                'plan_cycle' => $validated['plan_cycle'] ?? $store->plan_cycle,
            ]);

            $owner = User::find($store->user_id);
            if ($owner) {
                $owner->name = $validated['owner_name'];
                $owner->email = $validated['owner_email'];
                if (!empty($validated['owner_password'])) {
                    $owner->password = Hash::make($validated['owner_password']);
                }
                $owner->save();
            }
        });

        return redirect()->route('super.stores.index');
    }

    public function destroy(Store $store)
    {
        DB::transaction(function () use ($store) {
            // 0) Eliminar roles de la tienda
            $store->roles()->delete();

            // 1) Romper la referencia circular: quitar store_id a todos los usuarios de esta tienda
            User::where('store_id', $store->id)->update(['store_id' => null]);

            // 2) Guardamos el propietario y eliminamos la tienda
            $ownerId = $store->user_id;
            $store->delete();

            // 3) Eliminar al propietario (ya sin referencias)
            if ($ownerId) {
                User::where('id', $ownerId)->delete();
            }
        });

        return back();
    }
}
