<?php

use App\Enum\PermissionEnum;
use App\Models\Permission;
use App\Models\PlatformRole;
use InvalidArgumentException;

test('attaches a platform permission to the role', function () {
    $role = PlatformRole::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsDelete)->create();

    $role->grantPermission($permission);

    $this->assertDatabaseHas('platform_role_permissions', [
        'platform_role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
});

test('rejects an organisation permission', function () {
    $role = PlatformRole::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
})->throws(InvalidArgumentException::class, 'Platform roles may only receive platform permissions.');
