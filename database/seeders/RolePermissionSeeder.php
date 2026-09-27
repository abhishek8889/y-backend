<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
use App\Models\Role;
use App\Services\Organisation\OrganisationRoleService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Re-sync default permissions onto existing organisation roles.
     */
    public function run(): void
    {
        $roles = app(OrganisationRoleService::class);

        foreach (RoleEnum::cases() as $roleEnum) {
            Role::query()
                ->where('name', $roleEnum->value)
                ->each(function (Role $role) use ($roles, $roleEnum): void {
                    $roles->syncDefaultPermissions($role, $roleEnum);
                });
        }
    }
}
