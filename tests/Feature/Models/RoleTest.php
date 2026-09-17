<?php

use App\Enum\PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use InvalidArgumentException;

test('attaches an organisation permission to the role', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);

    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
});

test('rejects a platform permission', function () {
    $role = Role::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsView)->create();

    $role->grantPermission($permission);
})->throws(InvalidArgumentException::class, 'Organisation roles may only receive organisation permissions.');
