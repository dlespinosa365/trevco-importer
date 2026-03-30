<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FilamentAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('FILAMENT_ADMIN_PASSWORD', 'password');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
            ],
        );

        $user->syncRoles([Roles::ADMIN]);
    }
}
