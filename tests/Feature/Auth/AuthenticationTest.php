<?php

use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\Role;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use RuntimeException;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('organisation owners can authenticate and receive a jwt access token', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.authenticated'))
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $owner->id)
        ->assertJsonPath('data.user.email', $owner->email)
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::ORGANISATION->value)
        ->assertJsonPath('data.user.organisation_id', $organisation->id);

    $this->assertGuest();
});

test('users without an organisation membership cannot authenticate here', function () {
    $user = User::factory()->create();

    $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.organisation_login_forbidden'));

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('platform staff cannot authenticate through organisation login', function () {
    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $staff->assignRole($role);

    $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.organisation_login_forbidden'));
});

test('organisation staff receive their role, organisation scope, and organisation id', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create();
    $staff = OrganiserStaff::factory()->for($organisation)->for($user)->create();
    $role = Role::factory()->for($organisation)->create([
        'name' => 'Event Manager',
        'slug' => 'event_manager',
    ]);
    $staff->assignRole($role);

    $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::ORGANISATION->value)
        ->assertJsonPath('data.user.roles', ['event_manager'])
        ->assertJsonPath('data.user.organisation_id', $organisation->id);
});

test('organisation owners receive organisation scope without a staff role', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::ORGANISATION->value)
        ->assertJsonPath('data.user.roles', [])
        ->assertJsonPath('data.user.organisation_id', $organisation->id);
});

test('users cannot authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.failed'))
        ->assertJsonPath('error', __('auth.failed'));

    $this->assertGuest();
});

test('json clients receive 500 when login fails unexpectedly', function () {
    Exceptions::fake();

    $this->mock(AuthService::class, function ($mock): void {
        $mock->shouldReceive('login')->once()->andThrow(new RuntimeException('Database unavailable.'));
    });

    $this->postJson(route('organisation.login'), [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->assertServerError()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('exceptions.server_error'))
        ->assertJsonPath('error', 'Database unavailable.');

    Exceptions::assertReported(RuntimeException::class);
});

test('json clients receive 500 when jwt secret is missing', function () {
    config(['jwt.secret' => '']);

    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertServerError()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('exceptions.jwt_not_configured'))
        ->assertJsonPath('error', __('exceptions.jwt_not_configured'));
});

test('inactive users receive 403 when authenticating', function () {
    $owner = User::factory()->inactive()->create();
    Organisation::factory()->for($owner, 'owner')->create();

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.inactive'))
        ->assertJsonPath('error', __('auth.inactive'));

    $this->assertGuest();
});

test('login receives 422 when required fields are missing', function () {
    $this->postJson(route('organisation.login'), [])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.required', [
            'attribute' => __('validation.attributes.email'),
        ]))
        ->assertJsonPath('error', __('validation.required', [
            'attribute' => __('validation.attributes.email'),
        ]));
});

test('login receives 422 when the email is invalid', function () {
    $this->postJson(route('organisation.login'), [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.email', [
            'attribute' => __('validation.attributes.email'),
        ]))
        ->assertJsonPath('error', __('validation.email', [
            'attribute' => __('validation.attributes.email'),
        ]));
});

test('login receives 422 when the password is missing', function () {
    $this->postJson(route('organisation.login'), [
        'email' => 'user@example.com',
    ])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('validation.required', [
            'attribute' => __('validation.attributes.password'),
        ]))
        ->assertJsonPath('error', __('validation.required', [
            'attribute' => __('validation.attributes.password'),
        ]));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $organisation = Organisation::factory()->create();
    $owner = $organisation->owner;

    RateLimiter::increment(md5('login'.implode('|', [$owner->email, '127.0.0.1'])), amount: 5);

    $this->postJson(route('organisation.login'), [
        'email' => $owner->email,
        'password' => 'wrong-password',
    ])->assertTooManyRequests();
});
