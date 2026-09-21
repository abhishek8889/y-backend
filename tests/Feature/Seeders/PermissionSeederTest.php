<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Models\Permission;
use App\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;

test('seeds organisation crud permissions on the platform', function (PermissionEnum $permission, string $description) {
    $this->seed(PermissionSeeder::class);

    $row = Permission::query()->where('name', $permission->value)->first();

    expect($row)->not->toBeNull();
    expect($row->module)->toBe('organisations');
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

    expect(Permission::query()->where('name', PermissionEnum::OrganisationsRead->value)->value('scope'))
        ->toBe(PermissionScopeEnum::PLATFORM);

    expect(Permission::query()->where('name', PermissionEnum::PlatformSettingsUpdate->value)->value('module'))
        ->toBe('platform');

    expect(Permission::query()->forModule('organisations')->count())
        ->toBe(count(PermissionEnum::forModule('organisations')));

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

    $role = PlatformRole::query()->where('name', PlatformRoleEnum::SUPER_ADMIN)->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(
        array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            PermissionEnum::forScope(PermissionScopeEnum::PLATFORM),
        ),
    );
});
