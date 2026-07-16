<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's roles.
     */
    public function run(): void
    {
        foreach (['visitor', 'hotel_manager', 'ferry_operator', 'themepark_staff', 'admin'] as $role) {
            Role::findOrCreate($role);
        }
    }
}
