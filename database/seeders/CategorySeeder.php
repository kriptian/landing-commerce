<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Store;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Buscamos la primera tienda que exista
        $store = Store::first();

        if ($store) {
            // Creamos una categoría de ejemplo para esa tienda
            Category::create([
                'name' => 'Jaulas de Potencia',
                'store_id' => $store->id,
            ]);
        }
    }
}