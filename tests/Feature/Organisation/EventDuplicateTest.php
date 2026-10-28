<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\MediaTypeEnum;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;

test('organisation users can duplicate an event with tickets and draft offers', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $source = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Night Market',
        'description' => 'Original event',
        'capacity' => 500,
        'starts_at' => Carbon::parse('2026-11-07 23:00:00'),
        'ends_at' => Carbon::parse('2026-11-08 03:30:00'),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => Carbon::parse('2026-10-01 10:00:00'),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventImage::query()->create([
        'event_id' => $source->id,
        'type' => 'banner',
        'media_type' => MediaTypeEnum::IMAGE,
        'path' => 'events/'.$source->id.'/banner.jpg',
        'alt_text' => 'Banner',
        'sort_order' => 0,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $source->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'description' => 'GA ticket',
        'base_price' => 25.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'badge_label' => 'Popular',
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
        'price' => 20.00,
        'quantity_cap' => 50,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-20 23:59:59'),
        'max_per_order' => 4,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $soldOutTicket = EventTicket::query()->create([
        'event_id' => $source->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'VIP',
        'base_price' => 80.00,
        'currency' => 'GBP',
        'quantity_cap' => 20,
        'status' => EventTicketStatusEnum::SOLD_OUT,
        'sort_order' => 2,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    EventTicketOffer::query()->create([
        'event_ticket_id' => $soldOutTicket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'VIP Early',
        'price' => 70.00,
        'quantity_cap' => 10,
        'sale_starts_at' => Carbon::parse('2026-10-01 00:00:00'),
        'sale_ends_at' => Carbon::parse('2026-10-15 23:59:59'),
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::SOLD_OUT,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    $response = test()->withToken($token)
        ->postJson("/api/organisation/event/duplicate/{$source->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_duplicated'))
        ->assertJsonPath('data.name', 'Night Market')
        ->assertJsonPath('data.status', EventStatusEnum::DRAFT->value)
        ->assertJsonPath('data.timezone', 'Europe/London')
        ->assertJsonPath('data.published_at', null)
        ->assertJsonPath('data.completed_at', null)
        ->assertJsonPath('data.cancelled_at', null);

    $duplicatedId = (int) $response->json('data.id');
    $duplicatedUniqueId = $response->json('data.unique_id');

    expect($duplicatedId)->not->toBe($source->id)
        ->and($duplicatedUniqueId)->not->toBe($source->unique_id)
        ->and(Event::query()->where('organisation_id', $organisation->id)->count())->toBe(2);

    $duplicated = Event::query()->with(['images', 'tickets.offers'])->findOrFail($duplicatedId);

    expect($duplicated->images)->toHaveCount(1)
        ->and($duplicated->images->first()->path)->toBe('events/'.$source->id.'/banner.jpg')
        ->and($duplicated->tickets)->toHaveCount(2);

    $ga = $duplicated->tickets->firstWhere('name', 'General Admission');
    $vip = $duplicated->tickets->firstWhere('name', 'VIP');

    expect($ga)->not->toBeNull()
        ->and($ga->unique_id)->not->toBe($ticket->unique_id)
        ->and($ga->status)->toBe(EventTicketStatusEnum::ACTIVE)
        ->and($ga->offers)->toHaveCount(1)
        ->and($ga->offers->first()->name)->toBe('Early Bird')
        ->and($ga->offers->first()->status)->toBe(EventTicketOfferStatusEnum::DRAFT)
        ->and($ga->offers->first()->sale_starts_at)->toBeNull()
        ->and($ga->offers->first()->sale_ends_at)->toBeNull()
        ->and($ga->offers->first()->max_per_order)->toBe(4)
        ->and($vip)->not->toBeNull()
        ->and($vip->status)->toBe(EventTicketStatusEnum::DRAFT)
        ->and($vip->offers)->toHaveCount(1)
        ->and($vip->offers->first()->status)->toBe(EventTicketOfferStatusEnum::DRAFT)
        ->and($vip->offers->first()->sale_starts_at)->toBeNull()
        ->and($vip->offers->first()->sale_ends_at)->toBeNull();
});

test('duplicate returns 404 when event belongs to another organisation', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $otherOrganisation = Organisation::factory()->create();
    $otherEvent = Event::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Event',
        'status' => EventStatusEnum::DRAFT,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    test()->withToken($token)
        ->postJson("/api/organisation/event/duplicate/{$otherEvent->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});
