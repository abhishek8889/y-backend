<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Organisation;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;

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
        ->and($response->json('data.0.offers.0.name'))->toBe('Early Bird');
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
