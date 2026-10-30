<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderSalesChannelEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
use App\Models\OrganiserStaff;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Str;

/**
 * @return array{
 *     owner: User,
 *     organisation: Organisation,
 *     event: Event,
 *     ticket: EventTicket,
 *     token: string
 * }
 */
function customerOverviewFixture(): array
{
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $owner->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($owner)->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'The Summer Social',
        'status' => EventStatusEnum::PUBLISHED,
        'starts_at' => now()->addDays(14),
        'ends_at' => now()->addDays(14)->addHours(4),
        'timezone' => 'Europe/London',
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'base_price' => 40.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    return [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ];
}

test('customer overview requires authentication', function () {
    test()->getJson('/api/organisation/customer/1/overview')->assertUnauthorized();
});

test('organisation can view customer overview', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerOverviewFixture();

    $customer = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '07111111111',
        'country_code' => '+44',
        'country' => 'GB',
        'status' => StatusEnum::ACTIVE,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PARTIALLY_REFUNDED,
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
        'subtotal' => '80.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '8.00',
        'total_amount' => '80.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now()->subDay(),
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => 'General Admission',
        'quantity' => 2,
        'pricing_type' => OrderItemPricingTypeEnum::PAID,
        'unit_price' => '40.00',
        'discount_amount' => '0.00',
        'total_price' => '80.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '8.00',
        'organiser_share_amount' => '72.00',
        'currency' => 'GBP',
    ]);

    IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK', 12),
        'code' => 'YL'.strtoupper(Str::random(24)),
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => 'General Admission',
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);
    IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK', 12),
        'code' => 'YL'.strtoupper(Str::random(24)),
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => 'General Admission',
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '80.00',
        'currency' => 'GBP',
        'payment_method' => 'card',
        'card_brand' => 'visa',
        'card_last4' => '4242',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now()->subDay(),
    ]);

    Refund::query()->create([
        'unique_id' => UniqueIdGenerator::generate('REF', 12),
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'requested_by' => $owner->id,
        'approved_by' => $owner->id,
        'amount' => '20.00',
        'currency' => 'GBP',
        'reason' => 'Partial refund',
        'status' => RefundStatusEnum::SUCCEEDED,
        'refunded_at' => now()->subHours(2),
    ]);

    $membership = OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
        'first_order_id' => $order->id,
        'last_order_id' => $order->id,
        'status' => StatusEnum::ACTIVE,
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/overview')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_customer_overview'))
        ->assertJsonPath('data.customer.id', $customer->id)
        ->assertJsonPath('data.customer.first_name', 'Ada')
        ->assertJsonPath('data.customer.email', 'ada@example.com')
        ->assertJsonPath('data.customer.phone', '07111111111')
        ->assertJsonPath('data.customer.country_code', '+44')
        ->assertJsonPath('data.customer.country', 'GB')
        ->assertJsonPath('data.customer.status', StatusEnum::ACTIVE->value)
        ->assertJsonPath('data.customer.organisation_customer_status', StatusEnum::ACTIVE->value)
        ->assertJsonPath('data.customer.customer_since', $membership->created_at?->toIso8601String())
        ->assertJsonPath('data.summary.total_orders', 1)
        ->assertJsonPath('data.summary.total_tickets_purchased', 2)
        ->assertJsonPath('data.summary.total_spent', '80.00')
        ->assertJsonPath('data.summary.formatted_total_spent', '£80.00')
        ->assertJsonPath('data.summary.total_refunded', '20.00')
        ->assertJsonPath('data.summary.formatted_total_refunded', '£20.00')
        ->assertJsonPath('data.summary.events_attended', 0)
        ->assertJsonPath('data.summary.currency', 'GBP')
        ->assertJsonCount(2, 'data.recent_activity')
        ->assertJsonPath('data.recent_activity.0.type', 'refund')
        ->assertJsonCount(1, 'data.recent_orders')
        ->assertJsonPath('data.recent_orders.0.id', $order->id)
        ->assertJsonPath('data.recent_orders.0.payment.card_last4', '4242')
        ->assertJsonCount(1, 'data.upcoming_events')
        ->assertJsonPath('data.upcoming_events.0.id', $event->id)
        ->assertJsonPath('data.upcoming_events.0.name', 'The Summer Social')
        ->assertJsonStructure([
            'data' => [
                'customer' => [
                    'id',
                    'first_name',
                    'email',
                    'profile_image',
                    'phone',
                    'country_code',
                    'country',
                    'status',
                    'organisation_customer_status',
                    'customer_since',
                    'last_activity',
                ],
                'summary' => [
                    'total_orders',
                    'total_tickets_purchased',
                    'total_spent',
                    'formatted_total_spent',
                    'total_refunded',
                    'formatted_total_refunded',
                    'events_attended',
                    'currency',
                ],
                'recent_activity',
                'recent_orders',
                'upcoming_events',
            ],
        ]);
});

test('customer overview returns not found for unknown customer', function () {
    ['token' => $token] = customerOverviewFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/customer/999999/overview')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_customer_not_found'));
});
