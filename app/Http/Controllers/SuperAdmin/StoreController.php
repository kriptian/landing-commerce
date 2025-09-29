<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;

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
        ]);

        // Asignar store_id al propietario
        $owner->store_id = $store->id;
        $owner->save();

        // Asignar rol Administrador (para que vea el panel completo)
        $adminRole = SpatieRole::firstOrCreate(['name' => 'Administrador']);
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
            'promo_active' => 'sometimes|boolean',
            'promo_discount_percent' => 'sometimes|nullable|integer|min:1|max:90',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:users,email,' . $store->user_id,
            'owner_password' => 'nullable|string|min:8|confirmed',
        ]);

        DB::transaction(function () use ($store, $validated) {
            $store->update([
                'name' => $validated['name'],
                'max_users' => $validated['max_users'],
                'phone' => $validated['phone'],
                'promo_active' => $validated['promo_active'] ?? $store->promo_active,
                'promo_discount_percent' => $validated['promo_discount_percent'] ?? $store->promo_discount_percent,
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
