<?php

use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\VerificationOtpTypeEnum;
use App\Mail\AppMail;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\PlatformRole;
use App\Models\PlatformStaff;
use App\Models\User;
use App\Models\VerificationOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('send login otp stores verification without creating a user', function () {
    Mail::fake();

    test()->postJson('/api/public/send-login-otp', [
        'email' => 'buyer@example.com',
        'phone' => '7123456789',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.login_otp_sent'))
        ->assertJsonPath('data.email', 'buyer@example.com');

    $this->assertDatabaseMissing('users', [
        'email' => 'buyer@example.com',
    ]);

    $this->assertDatabaseHas('verification_otps', [
        'type' => VerificationOtpTypeEnum::LOGIN_TYPE->value,
        'email' => 'buyer@example.com',
        'phone' => '7123456789',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    Mail::assertSent(AppMail::class);
});

test('send login otp for existing user does not reset the password', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'phone' => '7000000000',
        'password' => 'password',
    ]);

    $originalPassword = $user->getAuthPassword();

    test()->postJson('/api/public/send-login-otp', [
        'email' => 'existing@example.com',
        'phone' => '7111111111',
        'first_name' => 'Existing',
        'last_name' => 'User',
    ])->assertOk();

    expect($user->fresh()->getAuthPassword())->toBe($originalPassword);

    $this->assertDatabaseHas('verification_otps', [
        'email' => 'existing@example.com',
        'type' => VerificationOtpTypeEnum::LOGIN_TYPE->value,
    ]);
});

test('verify login otp creates a new customer with a random password', function () {
    Mail::fake();

    test()->postJson('/api/public/send-login-otp', [
        'email' => 'buyer@example.com',
        'phone' => '7123456789',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ])->assertOk();

    VerificationOtp::query()
        ->where('email', 'buyer@example.com')
        ->update(['otp' => Hash::make('ABC123')]);

    test()->postJson('/api/public/verify-login-otp', [
        'email' => 'buyer@example.com',
        'otp' => 'ABC123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.authenticated'))
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'buyer@example.com')
        ->assertJsonPath('data.user.first_name', 'John')
        ->assertJsonPath('data.user.last_name', 'Doe')
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::CUSTOMER->value)
        ->assertJsonPath('data.user.roles', ['Customer'])
        ->assertJsonPath('data.user.organisation_id', null)
        ->assertJsonMissingPath('data.user.permissions');

    $this->assertDatabaseHas('users', [
        'email' => 'buyer@example.com',
        'phone' => '7123456789',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $this->assertDatabaseMissing('verification_otps', [
        'email' => 'buyer@example.com',
        'type' => VerificationOtpTypeEnum::LOGIN_TYPE->value,
    ]);
});

test('verify login otp for existing user does not change password', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => 'password',
    ]);
    $originalPassword = $user->getAuthPassword();

    test()->postJson('/api/public/send-login-otp', [
        'email' => 'existing@example.com',
        'phone' => '7123456789',
        'first_name' => 'Existing',
        'last_name' => 'User',
    ])->assertOk();

    VerificationOtp::query()
        ->where('email', 'existing@example.com')
        ->update(['otp' => Hash::make('XYZ789')]);

    test()->postJson('/api/public/verify-login-otp', [
        'email' => 'existing@example.com',
        'otp' => 'XYZ789',
    ])->assertOk();

    expect($user->fresh()->getAuthPassword())->toBe($originalPassword);
});

test('verify login otp keeps organisation context for organiser users', function () {
    Mail::fake();

    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = $organisation->owner;
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    test()->postJson('/api/public/send-login-otp', [
        'email' => $user->email,
        'phone' => $user->phone ?? '7123456789',
    ])->assertOk();

    VerificationOtp::query()
        ->where('email', $user->email)
        ->update(['otp' => Hash::make('ORG123')]);

    test()->postJson('/api/public/verify-login-otp', [
        'email' => $user->email,
        'otp' => 'ORG123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::ORGANISATION->value)
        ->assertJsonPath('data.user.organisation_id', $organisation->id)
        ->assertJsonMissingPath('data.user.permissions');
});

test('verify login otp keeps platform context for platform staff', function () {
    Mail::fake();

    $user = User::factory()->create();
    $staff = PlatformStaff::factory()->for($user)->create();
    $role = PlatformRole::factory()->create(['name' => PlatformRoleEnum::SUPER_ADMIN->value]);
    $staff->assignRole($role);

    test()->postJson('/api/public/send-login-otp', [
        'email' => $user->email,
        'phone' => '7123456789',
    ])->assertOk();

    VerificationOtp::query()
        ->where('email', $user->email)
        ->update(['otp' => Hash::make('ADM123')]);

    test()->postJson('/api/public/verify-login-otp', [
        'email' => $user->email,
        'otp' => 'ADM123',
    ])
        ->assertOk()
        ->assertJsonPath('data.user.scope', PermissionScopeEnum::PLATFORM->value)
        ->assertJsonPath('data.user.roles.0', PlatformRoleEnum::SUPER_ADMIN->value)
        ->assertJsonMissingPath('data.user.permissions');
});

test('verify login otp rejects invalid codes', function () {
    Mail::fake();

    test()->postJson('/api/public/send-login-otp', [
        'email' => 'buyer@example.com',
        'phone' => '7123456789',
        'first_name' => 'John',
    ])->assertOk();

    test()->postJson('/api/public/verify-login-otp', [
        'email' => 'buyer@example.com',
        'otp' => 'WRONG1',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('auth.otp_invalid'));
});
