<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds only real reference data + the first admin.
     * No fake products or stock: stock must come from stock movements (Phase 5+).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UnitSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
