<?php

use App\Enum\StatusEnum;
use App\Models\User;

test('json clients receive a jwt access token', function () {
    $user = User::factory()->create();

    $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.authenticated'))
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.expires_in', 3600)
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'email'],
            ],
        ]);

    $this->assertGuest();
});

test('a jwt access token can load the authenticated user', function () {
    $user = User::factory()->create();

    $token = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonMissingPath('data.password');
});

test('json clients receive 401 when the access token is missing', function () {
    $this->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', __('auth.invalid_token'));
});

test('json clients receive 401 when the access token is invalid', function () {
    $this->withToken('not-a-jwt')
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', __('auth.invalid_token'));
});

test('json clients receive 401 when the access token has expired', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $token = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $this->travel(61)->minutes();

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', __('auth.token_expired'));
});

test('json clients receive 401 when the access token belongs to an inactive user', function () {
    $user = User::factory()->create();

    $token = $this->postJson(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $user->update(['status' => StatusEnum::INACTIVE]);

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', __('auth.invalid_token'));
});
