<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Support\UniqueIdGenerator;

test('organisation users can fetch venue page overview counts', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Active One',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Active Two',
        'status' => 'active',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Inactive One',
        'status' => 'inactive',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Draft One',
        'status' => 'draft',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $otherOrganisation = Organisation::factory()->create();
    Venue::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Other Org Venue',
        'status' => 'active',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->getJson('/api/organisation/venue/page-overview')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_page_overview'))
        ->assertJsonPath('data.total_venue', 4)
        ->assertJsonPath('data.active_venue', 2)
        ->assertJsonPath('data.inactive_venue', 1);
});
