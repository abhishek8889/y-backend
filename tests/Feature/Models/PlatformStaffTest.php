<?php

use App\Models\OrganiserStaff;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;

test('assigns a platform role', function () {
    $staff = PlatformStaff::factory()->create();
    $role = PlatformRole::factory()->create();

    $staff->assignRole($role);

    $this->assertDatabaseHas('platform_staff_roles', [
        'platform_staff_id' => $staff->id,
        'platform_role_id' => $role->id,
    ]);
});

test('does not create an organisation membership', function () {
    $staff = PlatformStaff::factory()->create();

    expect(OrganiserStaff::query()->where('user_id', $staff->user_id)->exists())->toBeFalse();
});
