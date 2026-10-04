<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Permission;
use App\Models\User;

test('approved organisations can list organisation-scoped permissions only', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $venuesRead = Permission::factory()->named(PermissionEnum::VenuesRead)->create();
    $eventsCreate = Permission::factory()->named(PermissionEnum::EventsCreate)->create();
    Permission::factory()->named(PermissionEnum::OrganisationsRead)->create();

    $token = $this->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = $this->withToken($token)
        ->getJson('/api/organisation/role-permission/permissions-list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_permissions_list'));

    $permissions = $response->json('data');

    expect($permissions)->toHaveCount(2)
        ->and(collect($permissions)->pluck('name')->all())->toEqualCanonicalizing([
            PermissionEnum::VenuesRead->value,
            PermissionEnum::EventsCreate->value,
        ])
        ->and(collect($permissions)->every(
            fn (array $permission): bool => $permission['scope'] === PermissionScopeEnum::ORGANISATION->value,
        ))->toBeTrue();

    $venuesPermission = collect($permissions)->firstWhere('name', PermissionEnum::VenuesRead->value);

    expect($venuesPermission)->toMatchArray([
        'id' => $venuesRead->id,
        'module' => $venuesRead->module,
        'name' => $venuesRead->name,
        'scope' => PermissionScopeEnum::ORGANISATION->value,
        'description' => $venuesRead->description,
    ]);

    expect(collect($permissions)->firstWhere('name', PermissionEnum::EventsCreate->value)['id'])
        ->toBe($eventsCreate->id);
});

test('organisation permissions list returns 403 when the organisation is not approved', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => false,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $token = $this->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/organisation/role-permission/permissions-list')
        ->assertForbidden()
        ->assertJsonPath('message', __('messages.organisation_not_approved'));
});

test('organisation permissions list returns 401 when the access token is missing', function () {
    $this->getJson('/api/organisation/role-permission/permissions-list')
        ->assertUnauthorized()
        ->assertJsonPath('message', __('auth.invalid_token'));
});
