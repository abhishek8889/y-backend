<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Support\UniqueIdGenerator;

test('organisation users can activate and deactivate their venue', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Status Venue',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/manage-status', [
            'venue_id' => $venue->id,
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_status_updated'))
        ->assertJsonPath('data.id', $venue->id)
        ->assertJsonPath('data.status', 'inactive');

    expect($venue->fresh()->status)->toBe('inactive');
});

test('manage status rejects venue from another organisation', function () {
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
        ->postJson('/api/organisation/venue/manage-status', [
            'venue_id' => $otherVenue->id,
            'status' => 'inactive',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_not_found'));
});

test('manage status rejects invalid status values', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Status Venue',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/manage-status', [
            'venue_id' => $venue->id,
            'status' => 'draft',
        ])
        ->assertUnprocessable();
});
