<?php

use App\Models\EventCategory;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;

test('organisation can create a custom event category', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/event/create-custom-category', [
            'name' => 'Esports Night',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_category_created'))
        ->assertJsonPath('data.name', 'Esports Night')
        ->assertJsonPath('data.organisation_id', $organisation->id);

    expect(EventCategory::query()
        ->where('organisation_id', $organisation->id)
        ->where('name', 'Esports Night')
        ->exists())->toBeTrue();
});

test('custom event category rejects duplicate names for the same organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    EventCategory::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Local Festival',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/event/create-custom-category', [
            'name' => 'local festival',
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', __('messages.event_category_already_exists'));
});

test('custom event category rejects names that match system categories', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    EventCategory::query()->create([
        'organisation_id' => null,
        'name' => 'Live Music',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/event/create-custom-category', [
            'name' => 'Live Music',
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', __('messages.event_category_already_exists'));
});
