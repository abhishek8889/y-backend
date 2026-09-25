<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRolePermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;

test('assigns platform permissions to roles by name', function () {
    $this->seed([
        PermissionSeeder::class,
        PlatformRoleSeeder::class,
        PlatformRolePermissionSeeder::class,
    ]);

    $role = PlatformRole::query()->where('name', PlatformRoleEnum::SUPER_ADMIN->value)->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(
        array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            PermissionEnum::forScope(PermissionScopeEnum::PLATFORM),
        ),
    );
});
