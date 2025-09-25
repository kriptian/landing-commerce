<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'cristian.ospinagarcia@gmail.com');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password'),
                'is_admin' => true,
            ]
        );

        if (! $user->is_admin) {
            $user->is_admin = true;
            $user->save();
        }
    }
}


