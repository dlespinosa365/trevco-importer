<?php

namespace Database\Seeders;

use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate(Roles::ADMIN, 'web');
        Role::findOrCreate(Roles::USER, 'web');
    }
}
