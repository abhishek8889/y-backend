<?php

use App\Models\OrganiserStaff;
use App\Models\Role;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

test('assigns a role from the same organisation', function () {
    $staff = OrganiserStaff::factory()->create();
    $role = Role::factory()->for($staff->organisation)->create();

    $staff->assignRole($role);

    $this->assertDatabaseHas('staff_roles', [
        'organiser_staff_id' => $staff->id,
        'role_id' => $role->id,
    ]);
});

test('rejects a role from another organisation', function () {
    $staff = OrganiserStaff::factory()->create();
    $role = Role::factory()->create();

    $staff->assignRole($role);
})->throws(InvalidArgumentException::class, 'Staff may only receive roles from their organisation.');

test('rejects a second membership for the same user in an organisation', function () {
    $staff = OrganiserStaff::factory()->create();

    OrganiserStaff::factory()->for($staff->organisation)->for($staff->user)->create();
})->throws(QueryException::class);
