<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Le decimos que ejecute nuestros seeders en este orden
        $this->call([
            RolesAndPermissionsSeeder::class, // <-- AÑADIMOS ESTE DE PRIMERO
            SuperAdminSeeder::class,
            StoreSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}