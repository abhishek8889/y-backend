<?php

use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Models\VenueSuitableForOption;
use Illuminate\Support\Str;

test('organisation can create a custom suitable-for option', function () {
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
        ->postJson('/api/organisation/venue/create-custom-suitable-for-option', [
            'name' => 'Corporate Offsites',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_created'))
        ->assertJsonPath('data.name', 'Corporate Offsites')
        ->assertJsonPath('data.slug', 'corporate-offsites')
        ->assertJsonPath('data.organisation_id', $organisation->id)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('venue_suitable_for_options', [
        'organisation_id' => $organisation->id,
        'name' => 'Corporate Offsites',
        'slug' => 'corporate-offsites',
    ]);
});

test('custom suitable-for option creation rejects duplicate slug against system options', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    VenueSuitableForOption::query()->create([
        'organisation_id' => null,
        'name' => 'Weddings',
        'slug' => Str::slug('Weddings'),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/create-custom-suitable-for-option', [
            'name' => 'Weddings',
        ])
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_already_exists'));
});

test('custom suitable-for option creation rejects duplicate slug against own organisation options', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    VenueSuitableForOption::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Corporate Offsites',
        'slug' => 'corporate-offsites',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson('/api/organisation/venue/create-custom-suitable-for-option', [
            'name' => 'Corporate Offsites',
        ])
        ->assertStatus(400)
        ->assertJsonPath('message', __('messages.venue_suitable_for_option_already_exists'));
});

test('custom suitable-for option creation allows the same slug for a different organisation', function () {
    $otherOrganisation = Organisation::factory()->create();
    VenueSuitableForOption::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Corporate Offsites',
        'slug' => 'corporate-offsites',
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
        ->postJson('/api/organisation/venue/create-custom-suitable-for-option', [
            'name' => 'Corporate Offsites',
        ])
        ->assertOk()
        ->assertJsonPath('data.organisation_id', $organisation->id)
        ->assertJsonPath('data.slug', 'corporate-offsites');
});
