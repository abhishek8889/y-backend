<?php

use App\Models\Facility;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\DB;

test('organisation can delete its own custom facility and venue links are removed', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $facility = Facility::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Private Lounge',
        'slug' => 'private-lounge',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Grand Hall',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $venue->facilities()->attach($facility->id);

    expect(DB::table('venue_facilities')->where('facility_id', $facility->id)->exists())->toBeTrue();

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-facility/{$facility->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_facility_deleted'))
        ->assertJsonPath('data.facility_id', $facility->id);

    $this->assertDatabaseMissing('facilities', [
        'id' => $facility->id,
    ]);

    expect(DB::table('venue_facilities')->where('facility_id', $facility->id)->exists())->toBeFalse();
});

test('organisation cannot delete a system facility', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $facility = Facility::query()->create([
        'organisation_id' => null,
        'name' => 'Parking',
        'slug' => 'parking',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-facility/{$facility->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_facility_not_found'));

    $this->assertDatabaseHas('facilities', [
        'id' => $facility->id,
        'organisation_id' => null,
    ]);
});

test('organisation cannot delete another organisation facility', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $facility = Facility::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Other Lounge',
        'slug' => 'other-lounge',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-facility/{$facility->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_facility_not_found'));

    $this->assertDatabaseHas('facilities', [
        'id' => $facility->id,
        'organisation_id' => $otherOrganisation->id,
    ]);
});
