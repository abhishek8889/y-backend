<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PlatformRole;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    /**
     * Seed the default platform Super Admin role.
     */
    public function run(): void
    {
        $role = PlatformRole::query()->firstOrCreate([
            'name' => 'Super Admin',
        ]);

        $permissionIds = Permission::query()
            ->platform()
            ->pluck('id');

        $role->permissions()->sync($permissionIds);
    }
}
