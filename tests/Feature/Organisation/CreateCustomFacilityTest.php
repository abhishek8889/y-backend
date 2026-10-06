<?php

use App\Models\Facility;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use Illuminate\Support\Str;

test('organisation can create a custom facility', function () {
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
        ->postJson('/api/organisation/venue/create-custom-facilities', [
            'name' => 'Private Lounge',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_facility_created'))
        ->assertJsonPath('data.name', 'Private Lounge')
        ->assertJsonPath('data.slug', 'private-lounge')
        ->assertJsonPath('data.organisation_id', $organisation->id)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('facilities', [
        'organisation_id' => $organisation->id,
        'name' => 'Private Lounge',
        'slug' => 'private-lounge',
    ]);
});

test('custom facility creation rejects duplicate slug against system facilities', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    Facility::query()->create([
        'organisation_id' => null,
        'name' => 'WiFi',
        'slug' => Str::slug('WiFi'),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/create-custom-facilities', [
            'name' => 'WiFi',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.venue_facility_already_exists'));
});

test('custom facility creation rejects duplicate slug against own organisation facilities', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    Facility::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Private Lounge',
        'slug' => 'private-lounge',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/create-custom-facilities', [
            'name' => 'Private Lounge',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.venue_facility_already_exists'));
});

test('custom facility creation allows the same slug for a different organisation', function () {
    $otherOrganisation = Organisation::factory()->create();
    Facility::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Private Lounge',
        'slug' => 'private-lounge',
        'is_active' => true,
        'sort_order' => 1,
    ]);

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
        ->postJson('/api/organisation/venue/create-custom-facilities', [
            'name' => 'Private Lounge',
        ])
        ->assertOk()
        ->assertJsonPath('data.organisation_id', $organisation->id)
        ->assertJsonPath('data.slug', 'private-lounge');
});
