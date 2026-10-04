<?php

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\RoleEnum;
use App\Enum\StatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\Role;
use App\Models\User;
use App\Services\JwtTokenService;
use Database\Seeders\PermissionSeeder;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

test('organisation members receive a jwt access token', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.authenticated'))
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_in', 3600)
        ->assertJsonPath('data.user.id', $owner->id)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'email',
                    'scope',
                    'roles',
                    'organisation_id',
                    'permissions',
                ],
            ],
        ]);

    $this->assertGuest();
});

test('owner login response includes organisation permissions grouped by module', function () {
    $this->seed(PermissionSeeder::class);

    $owner = User::factory()->create();
    $organisation = Organisation::factory()->for($owner, 'owner')->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($owner)->create();
    $role = Role::factory()->for($organisation)->create([
        'name' => RoleEnum::OWNER->label(),
        'slug' => RoleEnum::OWNER->value,
    ]);
    $staff->assignRole($role);

    $response = $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertOk();

    expect($response->json('data.user.scope'))->toBe(PermissionScopeEnum::ORGANISATION->value)
        ->and($response->json('data.user.permissions.venues'))->toEqualCanonicalizing([
            PermissionEnum::VenuesRead->value,
            PermissionEnum::VenuesCreate->value,
            PermissionEnum::VenuesUpdate->value,
            PermissionEnum::VenuesDelete->value,
        ])
        ->and($response->json('data.user.permissions.events'))->toEqualCanonicalizing([
            PermissionEnum::EventsRead->value,
            PermissionEnum::EventsCreate->value,
            PermissionEnum::EventsUpdate->value,
            PermissionEnum::EventsDelete->value,
        ])
        ->and($response->json('data.user.permissions'))->not->toHaveKey('organisations');
});

test('platform staff cannot log in through organisation login', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $staff->assignRole($role);

    $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('message', __('auth.organisation_login_forbidden'));
});

test('a jwt access token includes organisation role and scope claims', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create([
        'name' => 'Event Manager',
        'slug' => 'event_manager',
    ]);
    $staff->assignRole($role);

    $token = $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $payload = JWT::decode($token, new Key((string) config('jwt.secret'), (string) config('jwt.algo')));

    expect($payload->sub)->toBe((string) $user->id);
    expect($payload->scope)->toBe(PermissionScopeEnum::ORGANISATION->value);
    expect($payload->roles)->toEqual(['event_manager']);
    expect($payload->organisation_id)->toBe($organisation->id);
});

test('a jwt access token can load the authenticated user', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $token = $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $owner->id)
        ->assertJsonPath('data.email', $owner->email)
        ->assertJsonMissingPath('data.password');
});

test('about-me returns authenticated user scope roles and permissions', function () {
    $this->seed(PermissionSeeder::class);

    $owner = User::factory()->create();
    $organisation = Organisation::factory()->for($owner, 'owner')->create([
        'approve_status' => true,
    ]);
    $staff = OrganiserStaff::factory()->for($organisation)->for($owner)->create();
    $role = Role::factory()->for($organisation)->create([
        'name' => RoleEnum::OWNER->label(),
        'slug' => RoleEnum::OWNER->value,
    ]);
    $staff->assignRole($role);

    $token = $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson(route('auth.about-me'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.about_me'))
        ->assertJsonPath('data.user.id', $owner->id)
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::ORGANISATION->value)
        ->assertJsonPath('data.user.organisation_id', $organisation->id)
        ->assertJsonPath('data.user.roles', [RoleEnum::OWNER->value])
        ->assertJsonPath('data.user.organisation_approve_status', true)
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'profile_image',
                    'status',
                    'scope',
                    'roles',
                    'organisation_id',
                    'organisation_approve_status',
                    'permissions' => [
                        'events',
                        'venues',
                    ],
                ],
            ],
        ]);
});

test('about-me for super_admin omits organisation_id', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $staff->assignRole($role);

    $token = app(JwtTokenService::class)->issue($user, $user->loginContext());

    $this->withToken($token)
        ->getJson(route('auth.about-me'))
        ->assertOk()
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::PLATFORM->value)
        ->assertJsonPath('data.user.organisation_id', null)
        ->assertJsonPath('data.user.roles', [PlatformRoleEnum::SUPER_ADMIN->value]);
});

test('about-me requires a jwt access token', function () {
    $this->getJson(route('auth.about-me'))
        ->assertUnauthorized();
});

test('json clients receive 401 when the access token is missing', function () {
    $this->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});

test('json clients receive 401 when the access token is invalid', function () {
    $this->withToken('not-a-jwt')
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});

test('json clients receive 401 when the access token has expired', function () {
    $this->freezeTime();

    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $token = $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->travel(61)->minutes();

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.token_expired'))
        ->assertJsonPath('error', __('auth.token_expired'));
});

test('json clients receive 401 when the access token belongs to an inactive user', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $token = $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    $owner->update(['status' => StatusEnum::INACTIVE]);

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});
