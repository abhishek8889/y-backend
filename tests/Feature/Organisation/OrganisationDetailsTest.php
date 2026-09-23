<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;

test('authenticated organisation staff can fetch organisation details', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Ada Events',
        'email' => 'org@example.com',
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/organisation/details')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_details'))
        ->assertJsonPath('data.id', $organisation->id)
        ->assertJsonPath('data.name', 'Ada Events')
        ->assertJsonPath('data.email', 'org@example.com');
});

test('organisation details returns 404 when the user has no organisation', function () {
    $user = User::factory()->create();

    $token = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/organisation/details')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.organisation_not_found'))
        ->assertJsonPath('error', __('messages.organisation_not_found'));
});

test('organisation details returns 401 when the access token is missing', function () {
    $this->getJson('/api/organisation/details')
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});
