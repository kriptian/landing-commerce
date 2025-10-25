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
        $primary = env('SUPER_ADMIN_EMAIL', 'cristian.ospinagarcia@gmail.com');
        $extra = array_values(array_filter(array_map('trim', explode(',', (string) env('SUPER_ADMIN_EMAILS', '')))));
        $all = collect([$primary])->filter()->merge($extra)->unique()->values();

        foreach ($all as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Super Admin',
                    'password' => Hash::make(str()->random(16)),
                    'is_admin' => true,
                ]
            );

            if (! $user->is_admin) {
                $user->is_admin = true;
                $user->save();
            }
        }
    }
}