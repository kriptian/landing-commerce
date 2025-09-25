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

        // Creamos permisos finos por área (idempotente)
        $perms = [
            // Dashboard
            'ver dashboard',
            // Órdenes
            'ver ordenes', 'gestionar ordenes',
            // Reportes
            'ver reportes',
            // Inventario / Productos
            'crear productos', 'editar productos', 'eliminar productos', 'ver inventario',
            // Categorías
            'gestionar categorias',
            // Usuarios
            'gestionar usuarios',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // Creamos el rol de Vendedor y le damos algunos permisos
        $roleVendedor = Role::firstOrCreate(['name' => 'Vendedor']);
        $roleVendedor->syncPermissions([
            'ver dashboard',
            'ver ordenes',
            'ver reportes',
        ]);

        // Creamos el rol de Administrador y le damos todos los permisos
        $roleAdmin = Role::firstOrCreate(['name' => 'Administrador']);
        $roleAdmin->syncPermissions(Permission::all());
    }
}