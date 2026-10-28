<?php

test('timezone list is publicly available without auth', function () {
    $response = test()->getJson('/api/public/timezones')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.timezone_list'))
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'timezone',
                    'country_code',
                    'country',
                ],
            ],
        ]);

    $items = collect($response->json('data'));

    expect($items->count())->toBeGreaterThan(100);

    $london = $items->firstWhere('timezone', 'Europe/London');

    expect($london)->not->toBeNull()
        ->and($london['country_code'])->toBe('GB')
        ->and($london['country'])->toBe('United Kingdom');

    $utc = $items->firstWhere('timezone', 'UTC');

    expect($utc)->not->toBeNull()
        ->and($utc['country_code'])->toBeNull()
        ->and($utc['country'])->toBeNull();
});
