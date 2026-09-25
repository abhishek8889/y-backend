<?php

namespace Database\Seeders;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Models\Permission;
use App\Models\PlatformRole;
use Illuminate\Database\Seeder;
use RuntimeException;

class PlatformRolePermissionSeeder extends Seeder
{
    /**
     * Map platform role names to permission names.
     *
     * @return array<string, list<string>>
     */
    private function matrix(): array
    {
        return [
            PlatformRoleEnum::SUPER_ADMIN->value => array_map(
                fn (PermissionEnum $permission): string => $permission->value,
                PermissionEnum::forScope(PermissionScopeEnum::PLATFORM),
            ),
        ];
    }

    /**
     * Assign permissions to platform roles by name.
     */
    public function run(): void
    {
        foreach ($this->matrix() as $roleName => $permissionNames) {
            $role = PlatformRole::query()->where('name', $roleName)->first();

            if ($role === null) {
                throw new RuntimeException("Platform role [{$roleName}] was not found.");
            }

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id', 'name');

            foreach ($permissionNames as $permissionName) {
                if (! $permissionIds->has($permissionName)) {
                    throw new RuntimeException("Permission [{$permissionName}] was not found.");
                }
            }

            $role->permissions()->sync($permissionIds->values()->all());
        }
    }
}
