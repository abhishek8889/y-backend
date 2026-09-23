<?php

use App\Enum\PermissionEnum;
use App\Enum\PlatformRoleEnum;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\User;

test('platform users with organisations.read can list organisations', function () {
    $user = platformUserWithOrganisationsRead();

    $first = Organisation::factory()->create(['name' => 'First Org']);
    $second = Organisation::factory()->create(['name' => 'Second Org']);

    $token = platformToken($user);

    $this->withToken($token)
        ->getJson('/api/platform/organisation/list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.platform_organisation_list'))
        ->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('data.0.name', 'Second Org')
        ->assertJsonPath('data.1.id', $first->id)
        ->assertJsonPath('data.1.name', 'First Org');
});

test('platform users with organisations.read can fetch organisation details by id', function () {
    $user = platformUserWithOrganisationsRead();
    $owner = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);
    $organisation = Organisation::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Ada Events',
    ]);

    $this->withToken(platformToken($user))
        ->getJson('/api/platform/organisation/details/'.$organisation->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.platform_organisation_details'))
        ->assertJsonPath('data.id', $organisation->id)
        ->assertJsonPath('data.name', 'Ada Events')
        ->assertJsonPath('data.organiser.id', $owner->id)
        ->assertJsonPath('data.organiser.first_name', 'Ada')
        ->assertJsonPath('data.organiser.last_name', 'Lovelace')
        ->assertJsonPath('data.organiser.email', 'ada@example.com');
});

test('platform organisation details returns 404 when organisation is missing', function () {
    $user = platformUserWithOrganisationsRead();

    $this->withToken(platformToken($user))
        ->getJson('/api/platform/organisation/details/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.organisation_not_found'))
        ->assertJsonPath('error', __('messages.organisation_not_found'));
});

test('platform users without organisations.read cannot list organisations', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => 'support']);
    $staff->assignRole($role);

    $this->withToken(platformToken($user))
        ->getJson('/api/platform/organisation/list')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.permission_denied'))
        ->assertJsonPath('error', __('messages.permission_denied'));
});

test('non platform users cannot list organisations', function () {
    $user = User::factory()->create();

    $this->withToken(platformToken($user))
        ->getJson('/api/platform/organisation/list')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.platform_access_denied'))
        ->assertJsonPath('error', __('messages.platform_access_denied'));
});

test('organisation list returns 401 when the access token is missing', function () {
    $this->getJson('/api/platform/organisation/list')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});

function platformUserWithOrganisationsRead(): User
{
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $permission = Permission::factory()->named(PermissionEnum::OrganisationsRead)->create();
    $role->grantPermission($permission);
    $staff->assignRole($role);

    return $user;
}

function platformToken(User $user): string
{
    return test()->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');
}
