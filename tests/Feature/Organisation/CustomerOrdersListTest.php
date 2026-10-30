<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderSalesChannelEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
use App\Models\OrganiserStaff;
use App\Models\Payment;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
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
function customerOrdersListFixture(): array
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
        'name' => 'Summer Festival',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'VIP Pass',
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

/**
 * @return array{customer: User, membership: OrganisationCustomer, order: Order}
 */
function createCustomerOrder(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    User $customer,
    array $overrides = [],
): array {
    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => $overrides['status'] ?? OrderStatusEnum::PAID,
        'sales_channel' => $overrides['sales_channel'] ?? OrderSalesChannelEnum::ONLINE,
        'request_origin' => $overrides['request_origin'] ?? 'web',
        'subtotal' => '40.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '4.00',
        'total_amount' => '40.00',
        'currency' => 'GBP',
        'customer_name' => $overrides['customer_name'] ?? 'Ada Lovelace',
        'customer_email' => $overrides['customer_email'] ?? $customer->email,
        'customer_phone' => $customer->phone,
        'paid_at' => now(),
        'created_at' => $overrides['created_at'] ?? now(),
        'updated_at' => $overrides['created_at'] ?? now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $overrides['ticket_name'] ?? $ticket->name,
        'quantity' => 1,
        'unit_price' => '40.00',
        'discount_amount' => '0.00',
        'total_price' => '40.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '4.00',
        'organiser_share_amount' => '36.00',
        'currency' => 'GBP',
    ]);

    $membership = OrganisationCustomer::query()
        ->where('organisation_id', $organisation->id)
        ->where('user_id', $customer->id)
        ->first();

    if ($membership === null) {
        $membership = OrganisationCustomer::factory()->create([
            'organisation_id' => $organisation->id,
            'user_id' => $customer->id,
            'first_order_id' => $order->id,
            'last_order_id' => $order->id,
            'status' => StatusEnum::ACTIVE,
        ]);
    } else {
        $membership->update(['last_order_id' => $order->id]);
    }

    return [
        'customer' => $customer,
        'membership' => $membership,
        'order' => $order->fresh(),
    ];
}

test('customer orders list requires authentication', function () {
    test()->getJson('/api/organisation/customer/1/orders')->assertUnauthorized();
});

test('organisation can list orders for a linked customer', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerOrdersListFixture();

    $customer = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);

    ['order' => $order] = createCustomerOrder($organisation, $event, $ticket, $customer, [
        'request_origin' => 'ios-app',
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '40.00',
        'currency' => 'GBP',
        'payment_method' => 'card',
        'card_brand' => 'visa',
        'card_last4' => '4242',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);

    // Another customer's order must not appear.
    $otherCustomer = User::factory()->create(['email' => 'other@example.com']);
    createCustomerOrder($organisation, $event, $ticket, $otherCustomer, [
        'customer_name' => 'Other Buyer',
        'customer_email' => 'other@example.com',
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_customer_orders_list'))
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $order->id)
        ->assertJsonPath('data.items.0.unique_id', $order->unique_id)
        ->assertJsonPath('data.items.0.sales_channel', OrderSalesChannelEnum::ONLINE->value)
        ->assertJsonPath('data.items.0.request_origin', 'ios-app')
        ->assertJsonPath('data.items.0.currency_sign', '£')
        ->assertJsonPath('data.items.0.formatted_total', '£40.00')
        ->assertJsonPath('data.items.0.payment.payment_method', 'card')
        ->assertJsonPath('data.items.0.payment.card_brand', 'visa')
        ->assertJsonPath('data.items.0.payment.card_last4', '4242')
        ->assertJsonPath('data.items.0.items.0.ticket_name', 'VIP Pass')
        ->assertJsonPath('data.items.0.items.0.offer_name', null)
        ->assertJsonPath('data.items.0.items.0.quantity', 1)
        ->assertJsonPath('data.items.0.items.0.event.id', $event->id)
        ->assertJsonPath('data.items.0.items.0.event.name', 'Summer Festival')
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'unique_id',
                        'status',
                        'sales_channel',
                        'request_origin',
                        'currency_sign',
                        'formatted_total',
                        'paid_at',
                        'created_at',
                        'tickets_pdf_path',
                        'payment' => [
                            'payment_method',
                            'card_brand',
                            'card_last4',
                        ],
                        'items' => [
                            '*' => [
                                'id',
                                'ticket_name',
                                'offer_name',
                                'quantity',
                                'event' => [
                                    'id',
                                    'name',
                                ],
                            ],
                        ],
                    ],
                ],
                'pagination' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'has_more',
                ],
            ],
        ]);
});

test('customer orders list can filter by status search and created_at range', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerOrdersListFixture();

    $customer = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'UTC'));
    ['order' => $matched] = createCustomerOrder($organisation, $event, $ticket, $customer, [
        'status' => OrderStatusEnum::PAID,
        'sales_channel' => OrderSalesChannelEnum::BOX_OFFICE,
        'request_origin' => 'box-office-kiosk',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'ticket_name' => 'VIP Pass',
        'created_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    createCustomerOrder($organisation, $event, $ticket, $customer, [
        'status' => OrderStatusEnum::REFUNDED,
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
        'request_origin' => 'web',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => 'ada@example.com',
        'created_at' => now(),
    ]);

    Carbon::setTestNow();

    $query = http_build_query([
        'status' => OrderStatusEnum::PAID->value,
        'search' => 'box_office',
        'search_from' => '2026-10-01',
        'search_to' => '2026-10-02',
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?'.$query)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $matched->id);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?search='.urlencode('VIP Pass'))
        ->assertOk()
        ->assertJsonCount(2, 'data.items');

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?search='.urlencode('Summer Festival'))
        ->assertOk()
        ->assertJsonCount(2, 'data.items');

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?search=box-office-kiosk')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $matched->id);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?search=ada@example.com')
        ->assertOk()
        ->assertJsonCount(2, 'data.items');
});

test('customer orders list returns not found for unknown customer', function () {
    ['token' => $token] = customerOrdersListFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/customer/999999/orders')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_customer_not_found'));
});

test('customer orders list rejects invalid status and date range', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerOrdersListFixture();

    $customer = User::factory()->create(['email' => 'ada@example.com']);
    createCustomerOrder($organisation, $event, $ticket, $customer);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?status=not-a-status')
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.organisation_customer_orders_invalid_status'));

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/orders?search_from=2026-10-20&search_to=2026-10-01')
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.organisation_customer_orders_invalid_date'));
});
