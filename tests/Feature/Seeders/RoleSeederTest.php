<?php

use App\Enum\RoleEnum;
use App\Models\Organisation;
use App\Models\Role;
use Database\Seeders\RoleSeeder;

test('seeds owner role for every organisation', function () {
    $organisation = Organisation::factory()->create();

    $this->seed(RoleSeeder::class);

    $role = Role::query()
        ->where('organisation_id', $organisation->id)
        ->where('name', RoleEnum::OWNER->value)
        ->first();

    expect($role)->not->toBeNull();
});

test('does not create duplicate owner roles on reseed', function () {
    $organisation = Organisation::factory()->create();

    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(
        Role::query()
            ->where('organisation_id', $organisation->id)
            ->where('name', RoleEnum::OWNER->value)
            ->count(),
    )->toBe(1);
});
