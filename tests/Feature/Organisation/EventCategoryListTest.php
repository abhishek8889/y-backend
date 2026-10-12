<?php

use App\Models\EventCategory;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;

test('event categories requires authentication', function () {
    test()->getJson('/api/event/categories')
        ->assertUnauthorized();
});

test('authenticated organisation receives system and own event categories', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    EventCategory::query()->create([
        'organisation_id' => null,
        'name' => 'Live Music',
    ]);
    EventCategory::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Esports Night',
    ]);
    EventCategory::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'name' => 'Other Org Only',
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->getJson('/api/event/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_categories_list'));

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Live Music')
        ->and($names)->toContain('Esports Night')
        ->and($names)->not->toContain('Other Org Only');
});
