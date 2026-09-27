<?php

namespace App\Services\Organisation;

use App\Enum\RoleEnum;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Service;
use RuntimeException;

class OrganisationRoleService extends Service
{
    /**
     * Create default organisation roles and attach all default permissions.
     */
    public function provisionDefaultRoles(Organisation $organisation): Role
    {
        $ownerRole = null;

        foreach (RoleEnum::cases() as $roleEnum) {
            $role = Role::query()->firstOrCreate([
                'organisation_id' => $organisation->id,
                'name' => $roleEnum->value,
            ]);

            $this->syncDefaultPermissions($role, $roleEnum);

            if ($roleEnum === RoleEnum::OWNER) {
                $ownerRole = $role;
            }
        }

        if ($ownerRole === null) {
            throw new RuntimeException('Owner role could not be provisioned.');
        }

        return $ownerRole;
    }

    /**
     * Sync a role to its default permission pack.
     */
    public function syncDefaultPermissions(Role $role, ?RoleEnum $roleEnum = null): void
    {
        $roleEnum ??= RoleEnum::from($role->name);

        $permissionNames = array_map(
            fn ($permission): string => $permission->value,
            $roleEnum->defaultPermissions(),
        );

        if ($permissionNames === []) {
            $role->permissions()->sync([]);

            return;
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
