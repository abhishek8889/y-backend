<?php

use App\Enum\EventStatusEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\JwtTokenService;
use App\Support\UniqueIdGenerator;

function wishlistCustomerToken(User $user): string
{
    return app(JwtTokenService::class)->issue($user, [
        'scope' => PermissionScopeEnum::CUSTOMER->value,
        'roles' => ['Customer'],
        'organisation_id' => null,
    ]);
}

test('authenticated user can save an event to wishlist', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();
    $customer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->postJson('/api/public/move-to-wishlist', [
            'event_id' => $event->id,
            'organisation_id' => $organisation->id,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.wishlist_saved'))
        ->assertJsonPath('data.event_id', $event->id)
        ->assertJsonPath('data.organisation_id', $organisation->id);

    $this->assertDatabaseHas('wishlists', [
        'user_id' => $customer->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
    ]);
});

test('wishlist save requires authentication', function () {
    test()->postJson('/api/public/move-to-wishlist', [
        'event_id' => 1,
    ])->assertUnauthorized();
});

test('wishlist rejects unpublished events', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();
    $customer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Draft Event',
        'status' => EventStatusEnum::DRAFT,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->postJson('/api/public/move-to-wishlist', [
            'event_id' => $event->id,
        ])
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});

test('wishlist rejects duplicate event for the same user', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();
    $customer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Wishlist::query()->create([
        'user_id' => $customer->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->postJson('/api/public/move-to-wishlist', [
            'event_id' => $event->id,
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', __('messages.wishlist_already_exists'));
});

test('authenticated user can remove an event from wishlist', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();
    $customer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Wishlist::query()->create([
        'user_id' => $customer->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->postJson('/api/public/remove-from-wishlist', [
            'event_id' => $event->id,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.wishlist_removed'))
        ->assertJsonPath('data.event_id', $event->id);

    $this->assertDatabaseMissing('wishlists', [
        'user_id' => $customer->id,
        'event_id' => $event->id,
    ]);
});

test('authenticated user can get wishlist', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $otherEvent = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    Wishlist::query()->create([
        'user_id' => $customer->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
    ]);

    Wishlist::query()->create([
        'user_id' => $otherCustomer->id,
        'organisation_id' => $organisation->id,
        'event_id' => $otherEvent->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->getJson('/api/public/get-wishlist')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.wishlist_list'))
        ->assertJsonPath('data.items.0.event_id', $event->id)
        ->assertJsonPath('data.items.0.event.name', 'Wishlist Event')
        ->assertJsonCount(1, 'data.items')
        ->assertJsonStructure([
            'data' => [
                'items',
                'pagination' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'has_more',
                ],
            ],
        ]);
});

test('get wishlist requires authentication', function () {
    test()->getJson('/api/public/get-wishlist')->assertUnauthorized();
});

test('remove from wishlist returns not found when item is missing', function () {
    $customer = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $owner = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Missing Wishlist Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    test()->withToken(wishlistCustomerToken($customer))
        ->postJson('/api/public/remove-from-wishlist', [
            'event_id' => $event->id,
        ])
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.wishlist_not_found'));
});
