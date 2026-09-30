<?php

namespace App\Services\Organisation;

use App\Models\Permission;
use App\Models\User;
use App\Services\Service;
use Illuminate\Database\Eloquent\Collection;

class RolePermissionService extends Service
{
    /**
     * List all organisation-scoped permissions available for role assignment.
     *
     * @return array{permissions: Collection<int, Permission>}
     */
    public function getAllOrganisationPermissions(User $user): array
    {
        $permissions = Permission::query()
            ->organisation()
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'module', 'name', 'scope', 'description']);

        return [
            'permissions' => $permissions,
        ];
    }

    /**
     * Create an organisation role and attach the selected permissions.
     *
     * @param  array<string, mixed>  $data
     * @return array{role: mixed}
     */
    public function createRoleWithPermission(User $user, array $data): array
    {
        return [
            'role' => null,
        ];
    }
}
