<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Resetea los roles y permisos cacheados
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Creamos los permisos
        Permission::create(['name' => 'crear productos']);
        Permission::create(['name' => 'editar productos']);
        Permission::create(['name' => 'eliminar productos']);
        Permission::create(['name' => 'ver reportes']);
        Permission::create(['name' => 'gestionar usuarios']);
        Permission::create(['name' => 'gestionar categorias']);

        // Creamos el rol de Vendedor y le damos algunos permisos
        $roleVendedor = Role::create(['name' => 'Vendedor']);
        $roleVendedor->givePermissionTo([
            'crear productos',
            'editar productos',
        ]);

        // Creamos el rol de Administrador y le damos todos los permisos
        $roleAdmin = Role::create(['name' => 'Administrador']);
        $roleAdmin->givePermissionTo(Permission::all());
    }
}