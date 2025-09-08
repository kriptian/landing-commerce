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
        // Borra los usuarios, tiendas, etc. existentes para empezar de cero
        // y luego ejecuta los seeders en orden.
        $this->call([
            StoreSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}