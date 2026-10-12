<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAddress;
use App\Support\UniqueIdGenerator;

test('organisation users can duplicate a venue from their organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $source = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Mohali Stadium',
        'description' => 'Original venue',
        'maximum_capacity' => 100,
        'standing_capacity' => 60,
        'seated_capacity' => 40,
        'status' => 'active',
        'is_private_hire_available' => true,
        'private_hire_description' => 'Available on request',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    VenueAddress::query()->create([
        'venue_id' => $source->id,
        'address_line_1' => '1 Stadium Road',
        'city' => 'Mohali',
        'postal_code' => '160055',
        'country' => 'IN',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->postJson("/api/organisation/venue/duplicate/{$source->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_duplicated'))
        ->assertJsonPath('data.name', 'Mohali Stadium')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.maximum_capacity', 100)
        ->assertJsonPath('data.address.city', 'Mohali');

    $duplicatedId = $response->json('data.id');
    $duplicatedUniqueId = $response->json('data.unique_id');

    expect($duplicatedId)->not->toBe($source->id)
        ->and($duplicatedUniqueId)->not->toBe($source->unique_id)
        ->and(Venue::query()->where('organisation_id', $organisation->id)->count())->toBe(2);
});

test('duplicate returns 404 when venue belongs to another organisation', function () {
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
        ->postJson("/api/organisation/venue/duplicate/{$otherVenue->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_not_found'));
});
