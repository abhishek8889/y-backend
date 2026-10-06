<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuitableForOption;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\DB;

test('organisation can delete its own custom suitable-for option and venue links are removed', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $option = VenueSuitableForOption::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Corporate Offsites',
        'slug' => 'corporate-offsites',
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

    $venue->suitableForOptions()->attach($option->id);

    expect(DB::table('venue_suitable_for')->where('suitable_for_option_id', $option->id)->exists())->toBeTrue();

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-suitable-for-option/{$option->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_deleted'))
        ->assertJsonPath('data.option_id', $option->id);

    $this->assertDatabaseMissing('venue_suitable_for_options', [
        'id' => $option->id,
    ]);

    expect(DB::table('venue_suitable_for')->where('suitable_for_option_id', $option->id)->exists())->toBeFalse();
});

test('organisation cannot delete a system suitable-for option', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $option = VenueSuitableForOption::query()->create([
        'organisation_id' => null,
        'name' => 'Weddings',
        'slug' => 'weddings',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-suitable-for-option/{$option->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_not_found'));

    $this->assertDatabaseHas('venue_suitable_for_options', [
        'id' => $option->id,
        'organisation_id' => null,
    ]);
});

test('organisation cannot delete another organisation suitable-for option', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $option = VenueSuitableForOption::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Other Option',
        'slug' => 'other-option',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->deleteJson("/api/organisation/venue/delete-suitable-for-option/{$option->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_not_found'));

    $this->assertDatabaseHas('venue_suitable_for_options', [
        'id' => $option->id,
        'organisation_id' => $otherOrganisation->id,
    ]);
});
