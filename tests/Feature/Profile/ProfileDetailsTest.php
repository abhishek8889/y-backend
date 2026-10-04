<?php

use App\Models\Organisation;
use App\Models\User;

test('authenticated users can fetch profile details', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '5551234567',
        'country_code' => '+1',
        'country' => 'US',
    ]);
    Organisation::factory()->for($user, 'owner')->create();

    $token = $this->postJson(route('organisation.login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson(route('profile.details'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.profile_details'))
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.last_name', 'Lovelace')
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.phone', '5551234567')
        ->assertJsonPath('data.country_code', '+1')
        ->assertJsonPath('data.country', 'US')
        ->assertJsonPath('data.profile_image', null)
        ->assertJsonMissingPath('data.password');
});

test('profile details returns 401 when the access token is missing', function () {
    $this->getJson(route('profile.details'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.invalid_token'))
        ->assertJsonPath('error', __('auth.invalid_token'));
});
