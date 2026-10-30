<?php

use App\Models\EventCategory;
use App\Models\Organisation;

test('event categories is publicly available without auth', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

    EventCategory::query()->create([
        'organisation_id' => null,
        'name' => 'Live Music',
    ]);
    EventCategory::query()->create([
        'organisation_id' => $organisation->id,
        'name' => 'Esports Night',
    ]);

    $response = test()->getJson('/api/event/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_categories_list'));

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Live Music')
        ->and($names)->not->toContain('Esports Night');
});

test('event categories only returns system categories', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $otherOrganisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);

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

    $response = test()->getJson('/api/event/categories')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_categories_list'));

    $names = collect($response->json('data'))->pluck('name')->all();

    expect($names)->toContain('Live Music')
        ->and($names)->not->toContain('Esports Night')
        ->and($names)->not->toContain('Other Org Only');
});
