<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('public can retrieve tickets and offers for a published event', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));

    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Summer Festival',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $activeTicket = EventTicket::query()->create([
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

    EventTicketOffer::query()->create([
        'event_ticket_id' => $activeTicket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
        'price' => 20.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-06 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicketOffer::query()->create([
        'event_ticket_id' => $activeTicket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Draft Offer',
        'price' => 15.00,
        'quantity_cap' => 10,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::DRAFT,
        'sort_order' => 2,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'Draft Ticket',
        'base_price' => 10.00,
        'currency' => 'GBP',
        'quantity_cap' => 20,
        'status' => EventTicketStatusEnum::DRAFT,
        'sort_order' => 2,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $response = test()->getJson("/api/public/events/tickets/{$event->unique_id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.public_event_tickets'))
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.name'))->toBe('General Admission')
        ->and($response->json('data.0.offers'))->toHaveCount(1)
        ->and($response->json('data.0.offers.0.name'))->toBe('Early Bird')
        ->and($response->json('data.0.offers.0.remaining_quantity'))->toBe(50);
});

test('public tickets return remaining quantity for available, partial, and sold out offers', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00'));

    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = User::factory()->create();
    $buyer = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Inventory Festival',
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
        'quantity_cap' => 500,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $availableOffer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Available Offer',
        'price' => 20.00,
        'quantity_cap' => 100,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $partialOffer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Partial Offer',
        'price' => 22.00,
        'quantity_cap' => 200,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 2,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $soldOutOffer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Sold Out Offer',
        'price' => 25.00,
        'quantity_cap' => 10,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::SOLD_OUT,
        'sort_order' => 3,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $paidOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $buyer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '3250.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '0.00',
        'total_amount' => '3250.00',
        'currency' => 'GBP',
        'customer_name' => 'Paid Buyer',
        'customer_email' => 'paid@example.com',
    ]);

    OrderItem::query()->create([
        'order_id' => $paidOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $partialOffer->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $partialOffer->name,
        'quantity' => 150,
        'unit_price' => '22.00',
        'discount_amount' => '0.00',
        'total_price' => '3300.00',
        'platform_fee_rate' => '0.00',
        'platform_share_amount' => '0.00',
        'organiser_share_amount' => '3300.00',
        'currency' => 'GBP',
    ]);

    OrderItem::query()->create([
        'order_id' => $paidOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $soldOutOffer->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $soldOutOffer->name,
        'quantity' => 10,
        'unit_price' => '25.00',
        'discount_amount' => '0.00',
        'total_price' => '250.00',
        'platform_fee_rate' => '0.00',
        'platform_share_amount' => '0.00',
        'organiser_share_amount' => '250.00',
        'currency' => 'GBP',
    ]);

    $cancelledOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $buyer->id,
        'status' => OrderStatusEnum::CANCELLED,
        'subtotal' => '2000.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '0.00',
        'total_amount' => '2000.00',
        'currency' => 'GBP',
        'customer_name' => 'Cancelled Buyer',
        'customer_email' => 'cancelled@example.com',
    ]);

    OrderItem::query()->create([
        'order_id' => $cancelledOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $availableOffer->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $availableOffer->name,
        'quantity' => 100,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '2000.00',
        'platform_fee_rate' => '0.00',
        'platform_share_amount' => '0.00',
        'organiser_share_amount' => '2000.00',
        'currency' => 'GBP',
    ]);

    $response = test()->getJson("/api/public/events/tickets/{$event->unique_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonCount(3, 'data.0.offers');

    $offersByName = collect($response->json('data.0.offers'))->keyBy('name');

    expect($offersByName['Available Offer']['remaining_quantity'])->toBe(100)
        ->and($offersByName['Partial Offer']['remaining_quantity'])->toBe(50)
        ->and($offersByName['Sold Out Offer']['remaining_quantity'])->toBe(0);
});

test('public tickets only return offers within their sale date range', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-06 15:30:00'));

    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Date Range Festival',
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

    EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Current Offer',
        'price' => 20.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-05 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-08 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Future Offer',
        'price' => 18.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-07 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-10 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 2,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Expired Offer',
        'price' => 15.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-06 12:00:00'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 3,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $response = test()->getJson("/api/public/events/tickets/{$event->unique_id}")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.offers'))->toHaveCount(1)
        ->and($response->json('data.0.offers.0.name'))->toBe('Current Offer');
});

test('public tickets endpoint returns not found for non-published events', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => true,
    ]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Draft Festival',
        'status' => EventStatusEnum::DRAFT,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicket::query()->create([
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

    test()->getJson("/api/public/events/tickets/{$event->unique_id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});

test('public tickets endpoint returns not found for unapproved organisation events', function () {
    $organisation = Organisation::factory()->create([
        'approve_status' => false,
    ]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Hidden Festival',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    test()->getJson("/api/public/events/tickets/{$event->unique_id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});
