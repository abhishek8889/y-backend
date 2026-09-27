<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            PlatformRoleSeeder::class,
            PlatformRolePermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            VenueTypeSeeder::class,
            FacilitySeeder::class,
            VenueSuitableForOptionSeeder::class,
            EventCategorySeeder::class,
        ]);
    }
}
