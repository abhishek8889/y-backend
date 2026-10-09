<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Organisation;
use App\Models\User;
use App\Services\JwtTokenService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;

function customerToken(User $user): string
{
    return app(JwtTokenService::class)->issue($user, [
        'scope' => PermissionScopeEnum::CUSTOMER->value,
        'roles' => ['Customer'],
        'organisation_id' => null,
    ]);
}

/**
 * @return array{user: User, offer: EventTicketOffer}
 */
function publishedBookableOffer(array $offerOverrides = []): array
{
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Booking Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'base_price' => 25.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $offer = EventTicketOffer::query()->create(array_merge([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
        'price' => 20.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
        'max_per_order' => 5,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ], $offerOverrides));

    return [
        'user' => $user,
        'offer' => $offer,
    ];
}

test('start booking requires authentication', function () {
    test()->postJson('/api/public/start-booking-ticket', [
        'offers' => [
            ['offer_id' => 1, 'qty' => 1],
        ],
    ])->assertUnauthorized();
});

test('authenticated user can start booking with a valid offer', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));

    ['offer' => $offer] = publishedBookableOffer();
    $customer = User::factory()->create();

    test()->withToken(customerToken($customer))
        ->postJson('/api/public/start-booking-ticket', [
            'offers' => [
                ['offer_id' => $offer->id, 'qty' => 2],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_booking_started'))
        ->assertJsonPath('data.offers.0.offer_id', $offer->id)
        ->assertJsonPath('data.offers.0.qty', 2);
});

test('start booking rejects offers outside the sale window', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));

    ['offer' => $offer] = publishedBookableOffer([
        'sale_starts_at' => Carbon::parse('2026-10-07 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
    ]);
    $customer = User::factory()->create();

    test()->withToken(customerToken($customer))
        ->postJson('/api/public/start-booking-ticket', [
            'offers' => [
                ['offer_id' => $offer->id, 'qty' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.public_booking_offer_unavailable'));
});

test('start booking rejects expired offers', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 15:30:00'));

    ['offer' => $offer] = publishedBookableOffer([
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-06 12:00:00'),
    ]);
    $customer = User::factory()->create();

    test()->withToken(customerToken($customer))
        ->postJson('/api/public/start-booking-ticket', [
            'offers' => [
                ['offer_id' => $offer->id, 'qty' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.public_booking_offer_unavailable'));
});

test('start booking rejects draft offers', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));

    ['offer' => $offer] = publishedBookableOffer([
        'status' => EventTicketOfferStatusEnum::DRAFT,
    ]);
    $customer = User::factory()->create();

    test()->withToken(customerToken($customer))
        ->postJson('/api/public/start-booking-ticket', [
            'offers' => [
                ['offer_id' => $offer->id, 'qty' => 1],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.public_booking_offer_unavailable'));
});
