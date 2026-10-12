<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Support\UniqueIdGenerator;

test('lists only active venues for events with limited fields', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Active Hall',
        'maximum_capacity' => 120,
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Inactive Hall',
        'maximum_capacity' => 80,
        'status' => 'inactive',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $otherOrganisation = Organisation::factory()->create();
    Venue::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Other Org Venue',
        'maximum_capacity' => 200,
        'status' => 'active',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->getJson('/api/organisation/venue/venue-list-for-events')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_list_for_events'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Hall')
        ->assertJsonPath('data.0.maximum_capacity', 120)
        ->assertJsonStructure([
            'data' => [
                ['id', 'name', 'maximum_capacity'],
            ],
        ]);
});
