<?php

use App\Enum\PermissionEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Permission;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\Role;
use App\Models\User;

test('organisation owners have organisation permissions without a staff role', function () {
    $organisation = Organisation::factory()->create();

    expect($organisation->owner->hasOrganisationPermission($organisation, PermissionEnum::EventsCreate))->toBeTrue();
});

test('organisation owners do not receive platform permissions through an organisation check', function () {
    $organisation = Organisation::factory()->create();

    expect($organisation->owner->hasOrganisationPermission($organisation, PermissionEnum::OrganisationsSuspend))->toBeFalse();
});

test('inactive organisation owners do not have organisation permissions', function () {
    $owner = User::factory()->inactive()->create();
    $organisation = Organisation::factory()->for($owner, 'owner')->create();

    expect($owner->hasOrganisationPermission($organisation, PermissionEnum::EventsCreate))->toBeFalse();
});

test('staff members have a permission granted through an organisation role', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasOrganisationPermission($organisation, PermissionEnum::EventsCreate))->toBeTrue();
});

test('staff members do not have organisation permissions missing from their roles', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasOrganisationPermission($organisation, PermissionEnum::EventsDelete))->toBeFalse();
});

test('inactive staff members do not have organisation permissions', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create();
    $staff = OrganiserStaff::factory()->inactive()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasOrganisationPermission($organisation, PermissionEnum::EventsCreate))->toBeFalse();
});

test('staff members do not have permissions for another organisation', function () {
    $organisation = Organisation::factory()->create();
    $otherOrganisation = Organisation::factory()->create();
    $user = User::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasOrganisationPermission($otherOrganisation, PermissionEnum::EventsCreate))->toBeFalse();
});

test('platform staff have a permission granted through a platform role', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsSuspend)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasPlatformPermission(PermissionEnum::OrganisationsSuspend))->toBeTrue();
});

test('inactive platform staff do not have platform permissions', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->inactive()->for($user)->create();
    $role = PlatformRole::factory()->create();
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsSuspend)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasPlatformPermission(PermissionEnum::OrganisationsSuspend))->toBeFalse();
});

test('organisation staff do not receive platform permissions', function () {
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create();
    $permission = Permission::factory()->named(PermissionEnum::EventsCreate)->create();

    $role->grantPermission($permission);
    $staff->assignRole($role);

    expect($user->hasPlatformPermission(PermissionEnum::OrganisationsSuspend))->toBeFalse();
});
