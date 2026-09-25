<?php

namespace Database\Seeders;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\RoleEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    /**
     * Map organisation role names to permission names.
     *
     * @return array<string, list<string>>
     */
    private function matrix(): array
    {
        return [
            RoleEnum::OWNER->value => array_map(
                fn (PermissionEnum $permission): string => $permission->value,
                PermissionEnum::forScope(PermissionScopeEnum::ORGANISATION),
            ),
        ];
    }

    /**
     * Assign permissions to organisation roles by name.
     */
    public function run(): void
    {
        foreach ($this->matrix() as $roleName => $permissionNames) {
            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id', 'name');

            foreach ($permissionNames as $permissionName) {
                if (! $permissionIds->has($permissionName)) {
                    throw new RuntimeException("Permission [{$permissionName}] was not found.");
                }
            }

            $ids = $permissionIds->values()->all();

            Role::query()
                ->where('name', $roleName)
                ->each(function (Role $role) use ($ids): void {
                    $role->permissions()->sync($ids);
                });
        }
    }
}
