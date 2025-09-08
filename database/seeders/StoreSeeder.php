<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Store;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        // Creamos un usuario que será el dueño de la tienda
        $owner = User::factory()->create([
            'name' => 'Admin de Tienda',
            'email' => 'owner@tienda.com',
            'is_admin' => true, // Lo hacemos admin también
        ]);

        // Creamos la tienda y la asignamos al usuario que acabamos de crear
        Store::create([
            'name' => 'Gimnasio Sonico',
            'logo_url' => '/logos/sonico-logo.png',
            'user_id' => $owner->id,
        ]);
    }
}