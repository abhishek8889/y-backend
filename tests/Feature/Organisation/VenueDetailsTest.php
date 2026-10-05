<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Support\UniqueIdGenerator;

test('organisation users can fetch venue details for their own organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Grand Hall',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->getJson("/api/organisation/venue/details/{$venue->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_details'))
        ->assertJsonPath('data.id', $venue->id)
        ->assertJsonPath('data.organisation_id', $organisation->id)
        ->assertJsonPath('data.name', 'Grand Hall');
});

test('venue details returns 404 when the venue belongs to another organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $otherOrganisation = Organisation::factory()->create();
    $otherVenue = Venue::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Other Venue',
        'status' => 'active',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->getJson("/api/organisation/venue/details/{$otherVenue->id}")
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.venue_not_found'));
});

test('venue details returns 404 when the venue does not exist', function () {
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
        ->getJson('/api/organisation/venue/details/999999')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_not_found'));
});
