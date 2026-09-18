<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Permission;
use App\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;

test('seeds organisation crud permissions on the platform', function (PermissionEnum $permission, string $description) {
    $this->seed(PermissionSeeder::class);

    $row = Permission::query()->where('name', $permission->value)->first();

    expect($row)->not->toBeNull();
    expect($row->scope)->toBe(PermissionScopeEnum::PLATFORM);
    expect($row->description)->toBe($description);
})->with([
    'read' => [PermissionEnum::OrganisationsRead, 'Read organisation'],
    'create' => [PermissionEnum::OrganisationsCreate, 'Create organisation'],
    'update' => [PermissionEnum::OrganisationsUpdate, 'Update organisation'],
    'delete' => [PermissionEnum::OrganisationsDelete, 'Delete organisation'],
]);

test('creates a catalog row for each permission', function () {
    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->where('name', PermissionEnum::EventsCreate->value)->value('scope'))
        ->toBe(PermissionScopeEnum::ORGANISATION);

    expect(Permission::query()->where('name', PermissionEnum::OrganisationsRead->value)->value('scope'))
        ->toBe(PermissionScopeEnum::PLATFORM);

    expect(Permission::query()->count())->toBe(count(PermissionEnum::cases()));
});

test('removes permissions that are no longer in the catalog', function () {
    Permission::factory()->create(['name' => 'organisations.suspend']);

    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->where('name', 'organisations.suspend')->exists())->toBeFalse();
});

test('gives the super admin role every platform permission', function () {
    $this->seed([
        PermissionSeeder::class,
        PlatformRoleSeeder::class,
    ]);

    $role = PlatformRole::query()->where('name', 'Super Admin')->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())
        ->toContain(PermissionEnum::OrganisationsRead->value)
        ->toContain(PermissionEnum::OrganisationsCreate->value)
        ->toContain(PermissionEnum::OrganisationsUpdate->value)
        ->toContain(PermissionEnum::OrganisationsDelete->value)
        ->not->toContain(PermissionEnum::EventsCreate->value);
});
