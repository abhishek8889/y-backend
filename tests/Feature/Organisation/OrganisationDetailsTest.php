<?php

use App\Enum\StripeOnboardingStatusEnum;
use App\Models\Organisation;
use App\Models\OrganisationStripeAccount;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Services\JwtTokenService;

test('authenticated organisation owner can fetch their organisation details', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Ada Events',
        'email' => 'org@example.com',
        'approve_status' => false,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $token = $this->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/my-org/details')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_details'))
        ->assertJsonPath('data.id', $organisation->id)
        ->assertJsonPath('data.name', 'Ada Events')
        ->assertJsonPath('data.email', 'org@example.com')
        ->assertJsonPath('data.approve_status', false)
        ->assertJsonPath('data.stripe_connect_account_created', false);
});

test('my-org details reports stripe connect account created when a stripe account exists', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    OrganisationStripeAccount::query()->create([
        'organisation_id' => $organisation->id,
        'stripe_account_id' => 'acct_test_my_org',
        'account_type' => 'express',
        'country' => 'GB',
        'default_currency' => 'gbp',
        'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
    ]);

    $token = $this->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/my-org/details')
        ->assertOk()
        ->assertJsonPath('data.stripe_connect_account_created', true);
});

test('my-org details returns 404 when the user has no organisation', function () {
    $user = User::factory()->create();

    $token = app(JwtTokenService::class)->issue($user, $user->loginContext());

    $this->withToken($token)
        ->getJson('/api/my-org/details')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.organisation_not_found'))
        ->assertJsonPath('error', __('messages.organisation_not_found'));
});

test('my-org details returns 401 when the access token is missing', function () {
    $this->getJson('/api/my-org/details')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});
