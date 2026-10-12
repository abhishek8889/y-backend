<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganiserAccount;
use App\Models\OrganiserLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrganiserBalanceService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('reserved organiser balance becomes available after event completed hold days', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00'));

    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Completed Event',
        'status' => EventStatusEnum::COMPLETED,
        'starts_at' => Carbon::parse('2026-10-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-10-01 18:00:00'),
        'completed_at' => Carbon::parse('2026-10-01 19:00:00'),
        'published_at' => Carbon::parse('2026-09-01 10:00:00'),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'GA',
        'base_price' => 25.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $offer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Standard',
        'price' => 20.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $user->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '40.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '4.00',
        'total_amount' => '40.00',
        'currency' => 'GBP',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'paid_at' => now(),
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $offer->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $offer->name,
        'quantity' => 2,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '40.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '4.00',
        'organiser_share_amount' => '36.00',
        'currency' => 'GBP',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'payment_intent_id' => 'pi_release_test',
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '40.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);

    app(OrganiserBalanceService::class)->creditReservedEarnings($order->fresh('items'));

    $account = OrganiserAccount::query()->first();
    expect($account)->not->toBeNull()
        ->and((string) $account->total_reserved)->toBe('36.00')
        ->and((string) $account->available_balance)->toBe('0.00');

    $this->artisan('organiser:release-reserved-balances')
        ->assertSuccessful();

    $account->refresh();

    expect((string) $account->total_reserved)->toBe('0.00')
        ->and((string) $account->available_balance)->toBe('36.00')
        ->and(OrganiserLedgerEntry::query()->where('entry_type', OrganiserLedgerEntryTypeEnum::PAYOUT_RELEASED)->count())->toBe(1)
        ->and($item->id)->toBeInt();
});

test('reserved balance stays reserved before hold days elapse', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));

    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Recent Event',
        'status' => EventStatusEnum::COMPLETED,
        'starts_at' => Carbon::parse('2026-10-01 10:00:00'),
        'ends_at' => Carbon::parse('2026-10-01 18:00:00'),
        'completed_at' => Carbon::parse('2026-10-01 19:00:00'),
        'published_at' => Carbon::parse('2026-09-01 10:00:00'),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'GA',
        'base_price' => 25.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $offer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Standard',
        'price' => 20.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $user->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '0.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $offer->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $offer->name,
        'quantity' => 1,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '0.00',
        'platform_share_amount' => '0.00',
        'organiser_share_amount' => '20.00',
        'currency' => 'GBP',
    ]);

    app(OrganiserBalanceService::class)->creditReservedEarnings($order->fresh('items'));

    $this->artisan('organiser:release-reserved-balances')
        ->assertSuccessful();

    $account = OrganiserAccount::query()->first();

    expect((string) $account->total_reserved)->toBe('20.00')
        ->and((string) $account->available_balance)->toBe('0.00')
        ->and(OrganiserLedgerEntry::query()->where('entry_type', OrganiserLedgerEntryTypeEnum::PAYOUT_RELEASED)->count())->toBe(0);
});
