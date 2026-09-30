<?php

use App\Enum\PermissionEnum;
use App\Enum\RoleEnum;
use App\Models\Organisation;
use App\Models\Role;
use App\Services\Organisation\OrganisationRoleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

test('seeds owner role with all organisation-scoped permissions', function () {
    $organisation = Organisation::factory()->create();

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);

    $role = Role::query()
        ->where('organisation_id', $organisation->id)
        ->where('name', RoleEnum::OWNER->value)
        ->first();

    expect($role)->not->toBeNull();
    expect($role->description)->toBe(RoleEnum::OWNER->description());
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(
        array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            RoleEnum::OWNER->defaultPermissions(),
        ),
    );
});

test('does not create duplicate owner roles on reseed', function () {
    $organisation = Organisation::factory()->create();

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
    ]);
    $this->seed(RoleSeeder::class);

    expect(
        Role::query()
            ->where('organisation_id', $organisation->id)
            ->where('name', RoleEnum::OWNER->value)
            ->count(),
    )->toBe(1);
});

test('role permission seeder resyncs owner organisation permissions', function () {
    $organisation = Organisation::factory()->create();

    $this->seed([
        PermissionSeeder::class,
        RoleSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $role = Role::query()
        ->where('organisation_id', $organisation->id)
        ->where('name', RoleEnum::OWNER->value)
        ->first();

    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(
        array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            RoleEnum::OWNER->defaultPermissions(),
        ),
    );
});

test('provisioning on a new organisation grants all organisation-scoped permissions', function () {
    $this->seed(PermissionSeeder::class);

    $organisation = Organisation::factory()->create();
    $role = app(OrganisationRoleService::class)->provisionDefaultRoles($organisation);

    expect($role->name)->toBe(RoleEnum::OWNER->value);
    expect($role->description)->toBe(RoleEnum::OWNER->description());
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing([
        PermissionEnum::VenuesRead->value,
        PermissionEnum::VenuesCreate->value,
        PermissionEnum::VenuesUpdate->value,
        PermissionEnum::VenuesDelete->value,
        PermissionEnum::EventsRead->value,
        PermissionEnum::EventsCreate->value,
        PermissionEnum::EventsUpdate->value,
        PermissionEnum::EventsDelete->value,
    ]);
});
