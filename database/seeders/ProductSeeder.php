<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Store;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscamos la primera tienda y la primera categoría que existan
        $store = Store::first();
        $category = Category::first();

        if ($store && $category) {
            // Creamos nuestro primer producto de prueba
            Product::create([
                'name' => 'Jaula Profesional®',
                'price' => 7500.00,
                'short_description' => 'Estructura robusta para entrenamiento de fuerza de alto rendimiento.',
                'long_description' => 'Diseñada para los gimnasios más exigentes, nuestra Jaula Profesional ofrece la máxima seguridad y versatilidad. Fabricada con acero de alta resistencia y acabados de primera calidad, es la pieza central perfecta para cualquier rutina de levantamiento de pesas.',
                'specifications' => json_encode([
                    'Alto: 2.30m',
                    'Ancho: 1.20m',
                    'Profundidad: 1.50m',
                    'Material: Acero estructural',
                    'Incluye: Soportes de seguridad, barra de dominadas multiagarre'
                ]),
                'main_image_url' => '/images/jaula-profesional.jpg',
                'store_id' => $store->id,
                'category_id' => $category->id,
            ]);
        }
    }
}