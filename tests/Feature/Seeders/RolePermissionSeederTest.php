<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\RoleEnum;
use App\Models\Organisation;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;

test('assigns organisation permissions to owner roles by name', function () {
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

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(
        array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            PermissionEnum::forScope(PermissionScopeEnum::ORGANISATION),
        ),
    );
});
