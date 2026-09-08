<?php

use App\Models\Role;
use App\Models\Store;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stores') || ! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'store_id')) {
            return;
        }

        // Crear el rol "physical-sales" para todas las tiendas existentes
        $stores = Store::all();

        foreach ($stores as $store) {
            // Verificar si el rol ya existe para esta tienda usando el modelo Role directamente
            // Usamos DB para evitar las validaciones de Spatie que no consideran store_id
            $existingRole = \Illuminate\Support\Facades\DB::table('roles')
                ->where('name', 'physical-sales')
                ->where('store_id', $store->id)
                ->where('guard_name', config('auth.defaults.guard', 'web'))
                ->first();

            if (! $existingRole) {
                // Insertar directamente en la tabla para evitar validaciones de Spatie
                \Illuminate\Support\Facades\DB::table('roles')->insert([
                    'name' => 'physical-sales',
                    'store_id' => $store->id,
                    'guard_name' => config('auth.defaults.guard', 'web'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Eliminar el rol "physical-sales" de todas las tiendas
        Role::where('name', 'physical-sales')
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->delete();

        // Limpiar caché de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
