<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Importante que esta línea esté

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'cristian.ospinagarcia@gmail.com');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Cristian Ospina', // <-- AJUSTE 1: Tu nombre
                'password' => Hash::make('CrisDeveloper25**'), // <-- AJUSTE 2: Contraseña
                'is_admin' => true,
            ]
        );

        // Esta parte que ya tenías está perfecta, se asegura de que seas admin sí o sí
        if (! $user->is_admin) {
            $user->is_admin = true;
            $user->save();
        }
    }
}