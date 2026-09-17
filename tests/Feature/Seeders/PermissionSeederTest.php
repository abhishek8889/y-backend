<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Permission;
use App\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;

test('creates a catalog row for each permission', function () {
    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->where('name', PermissionEnum::EventsCreate->value)->value('scope'))
        ->toBe(PermissionScopeEnum::ORGANISATION);

    expect(Permission::query()->where('name', PermissionEnum::OrganisationsSuspend->value)->value('scope'))
        ->toBe(PermissionScopeEnum::PLATFORM);

    expect(Permission::query()->count())->toBe(count(PermissionEnum::cases()));
});

test('gives the super admin role every platform permission', function () {
    $this->seed([
        PermissionSeeder::class,
        PlatformRoleSeeder::class,
    ]);

    $role = PlatformRole::query()->where('name', 'Super Admin')->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())
        ->toContain(PermissionEnum::OrganisationsSuspend->value)
        ->not->toContain(PermissionEnum::EventsCreate->value);
});
