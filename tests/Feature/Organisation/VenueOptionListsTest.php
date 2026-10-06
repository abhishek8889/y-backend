<?php

use App\Models\Facility;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\VenueSuitableForOption;
use App\Models\VenueType;

test('venue types remain publicly available', function () {
    VenueType::query()->create([
        'name' => 'Stadium',
        'slug' => 'stadium',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    VenueType::query()->create([
        'name' => 'Inactive Hall',
        'slug' => 'inactive-hall',
        'is_active' => false,
        'sort_order' => 2,
    ]);

    test()->getJson('/api/venue/types')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_types_list'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Stadium')
        ->assertJsonPath('data.0.slug', 'stadium');
});

test('organisation can list system and own facilities only', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    Facility::query()->create([
        'organisation_id' => null,
        'name' => 'Parking',
        'slug' => 'parking',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Facility::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Private Lounge',
        'slug' => 'private-lounge',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    Facility::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Other Org Facility',
        'slug' => 'other-org-facility',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    Facility::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Inactive Own Facility',
        'slug' => 'inactive-own-facility',
        'is_active' => false,
        'sort_order' => 4,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->getJson('/api/organisation/venue/facilities')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_facilities_list'))
        ->assertJsonCount(2, 'data');

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Parking', 'Private Lounge')
        ->and($names)->not->toContain('Other Org Facility', 'Inactive Own Facility');
});

test('organisation can list system and own suitable-for options only', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    VenueSuitableForOption::query()->create([
        'organisation_id' => null,
        'name' => 'Weddings',
        'slug' => 'weddings',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    VenueSuitableForOption::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Corporate Offsites',
        'slug' => 'corporate-offsites',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    VenueSuitableForOption::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Other Org Option',
        'slug' => 'other-org-option',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->getJson('/api/organisation/venue/suitable-for-options')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_suitable_for_options_list'))
        ->assertJsonCount(2, 'data');

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Weddings', 'Corporate Offsites')
        ->and($names)->not->toContain('Other Org Option');
});

test('facilities and suitable-for options require authentication', function () {
    test()->getJson('/api/organisation/venue/facilities')
        ->assertUnauthorized();

    test()->getJson('/api/organisation/venue/suitable-for-options')
        ->assertUnauthorized();
});
