<?php

use App\Enum\PermissionEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

test('approved organisations can create a role with selected permissions', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $viewEvents = Permission::factory()->named(PermissionEnum::EventsRead)->create();
    $createEvents = Permission::factory()->named(PermissionEnum::EventsCreate)->create();
    $platformPermission = Permission::factory()->named(PermissionEnum::OrganisationsRead)->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = $this->withToken($token)
        ->postJson('/api/organisation/role-permission/create-role', [
            'name' => 'Event Manager',
            'description' => 'Manage events, ticketing and event settings.',
            'permission_ids' => [$viewEvents->id, $createEvents->id],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_role_created'))
        ->assertJsonPath('data.name', 'Event Manager')
        ->assertJsonPath('data.slug', 'event-manager')
        ->assertJsonPath('data.description', 'Manage events, ticketing and event settings.')
        ->assertJsonPath('data.organisation_id', $organisation->id);

    $permissionNames = collect($response->json('data.permissions'))->pluck('name')->all();

    expect($permissionNames)->toEqualCanonicalizing([
        PermissionEnum::EventsRead->value,
        PermissionEnum::EventsCreate->value,
    ]);

    $role = Role::query()
        ->where('organisation_id', $organisation->id)
        ->where('name', 'Event Manager')
        ->first();

    expect($role)->not->toBeNull();

    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $viewEvents->id,
    ]);
    $this->assertDatabaseHas('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $createEvents->id,
    ]);
    $this->assertDatabaseMissing('role_permissions', [
        'role_id' => $role->id,
        'permission_id' => $platformPermission->id,
    ]);
});

test('create role rejects reserved owner name', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->postJson('/api/organisation/role-permission/create-role', [
            'name' => 'owner',
            'permission_ids' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.organisation_role_name_reserved'));
});

test('create role rejects platform permission ids', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $platformPermission = Permission::factory()->named(PermissionEnum::OrganisationsRead)->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->postJson('/api/organisation/role-permission/create-role', [
            'name' => 'Support',
            'permission_ids' => [$platformPermission->id],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.organisation_role_invalid_permissions'));
});
