<?php

namespace Database\Seeders;

use App\Enum\PlatformRoleEnum;
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
            'name' => PlatformRoleEnum::SUPER_ADMIN->value,
        ]);

        $permissionIds = Permission::query()
            ->platform()
            ->pluck('id');

        $role->permissions()->sync($permissionIds);
    }
}
