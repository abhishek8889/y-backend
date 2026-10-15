<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderSalesChannelEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\Payment;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Str;

/**
 * @return array{
 *     user: User,
 *     organisation: Organisation,
 *     event: Event,
 *     ticket: EventTicket,
 *     token: string
 * }
 */
function eventOrdersOrgFixture(): array
{
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($user)->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Org Event Orders',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'base_price' => 20.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $token = test()->postJson('/api/organisation/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->json('data.access_token');

    return [
        'user' => $user,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ];
}

/**
 * @return array{order: Order, scan_code: string}
 */
/**
 * @param  array{
 *     sales_channel?: OrderSalesChannelEnum,
 *     created_by?: int|null,
 *     provider?: PaymentProviderEnum,
 *     reference_name?: string|null,
 *     external_reference_id?: string|null,
 *     payment_intent_id?: string|null
 * }  $options
 * @return array{order: Order, scan_code: string, payment: Payment}
 */
function createPaidOrderForEvent(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    string $customerEmail = 'buyer@example.com',
    array $options = [],
): array {
    $salesChannel = $options['sales_channel'] ?? OrderSalesChannelEnum::ONLINE;
    $createdBy = $options['created_by'] ?? null;
    $provider = $options['provider'] ?? PaymentProviderEnum::STRIPE;

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'status' => OrderStatusEnum::PAID,
        'sales_channel' => $salesChannel,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Buyer Name',
        'customer_email' => $customerEmail,
        'customer_phone' => '07123456789',
        'paid_at' => now(),
        'created_by' => $createdBy,
    ]);

    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'offer_name' => 'Early Bird',
        'quantity' => 1,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    $scanCode = 'YL'.strtoupper(Str::random(24));

    IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK'),
        'code' => $scanCode,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'offer_name' => 'Early Bird',
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => $provider,
        'payment_intent_id' => $options['payment_intent_id'] ?? null,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '20.00',
        'currency' => 'GBP',
        'reference_name' => $options['reference_name'] ?? null,
        'external_reference_id' => $options['external_reference_id'] ?? null,
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);

    return [
        'order' => $order,
        'scan_code' => $scanCode,
        'payment' => $payment,
    ];
}

test('event orders list requires authentication', function () {
    test()->getJson('/api/organisation/event/orders/1')->assertUnauthorized();
});

test('organisation can list orders for its event with ticket scan codes', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    ['order' => $order, 'scan_code' => $scanCode] = createPaidOrderForEvent(
        $organisation,
        $event,
        $ticket,
    );

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/'.$event->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_orders_list'))
        ->assertJsonPath('data.items.0.unique_id', $order->unique_id)
        ->assertJsonPath('data.items.0.status', OrderStatusEnum::PAID->value)
        ->assertJsonPath('data.items.0.customer_email', 'buyer@example.com')
        ->assertJsonPath('data.items.0.items.0.ticket_name', 'General Admission')
        ->assertJsonPath('data.items.0.items.0.issued_tickets.0.code', $scanCode)
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

test('event orders list returns not found for another organisations event', function () {
    ['token' => $token] = eventOrdersOrgFixture();

    $otherOwner = User::factory()->create();
    $otherOrganisation = Organisation::factory()->create([
        'owner_id' => $otherOwner->id,
        'approve_status' => true,
    ]);

    $otherEvent = Event::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Org Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/'.$otherEvent->id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});

test('event orders list does not include orders from another organisation', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    ['order' => $ownOrder] = createPaidOrderForEvent($organisation, $event, $ticket, 'own@example.com');

    $otherOwner = User::factory()->create();
    $otherOrganisation = Organisation::factory()->create([
        'owner_id' => $otherOwner->id,
        'approve_status' => true,
    ]);
    $otherEvent = Event::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);
    $otherTicket = EventTicket::query()->create([
        'event_id' => $otherEvent->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'Other Ticket',
        'base_price' => 30.00,
        'currency' => 'GBP',
        'quantity_cap' => 50,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);
    ['order' => $otherOrder] = createPaidOrderForEvent(
        $otherOrganisation,
        $otherEvent,
        $otherTicket,
        'other@example.com',
    );

    $response = test()->withToken($token)
        ->getJson('/api/organisation/event/orders/'.$event->id)
        ->assertOk();

    $uniqueIds = collect($response->json('data.items'))->pluck('unique_id');

    expect($uniqueIds)->toContain($ownOrder->unique_id)
        ->and($uniqueIds)->not->toContain($otherOrder->unique_id)
        ->and($uniqueIds)->toHaveCount(1);
});

test('event orders list excludes pending orders by default', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'status' => OrderStatusEnum::PENDING,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Pending Buyer',
        'customer_email' => 'pending@example.com',
        'expires_at' => now()->addMinutes(15),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => 1,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/'.$event->id)
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

test('event order detail requires authentication', function () {
    test()->getJson('/api/organisation/event/orders/detail/1')->assertUnauthorized();
});

test('organisation can view order detail with customer payment channel and created_by', function () {
    [
        'user' => $user,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    ['order' => $order, 'scan_code' => $scanCode] = createPaidOrderForEvent(
        $organisation,
        $event,
        $ticket,
        'buyer@example.com',
        [
            'sales_channel' => OrderSalesChannelEnum::BOX_OFFICE,
            'created_by' => $user->id,
            'provider' => PaymentProviderEnum::CARD_TERMINAL,
            'reference_name' => 'SumUp Door 1',
            'external_reference_id' => 'TXN-849201',
        ],
    );

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/detail/'.$order->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_order_details'))
        ->assertJsonPath('data.unique_id', $order->unique_id)
        ->assertJsonPath('data.sales_channel', OrderSalesChannelEnum::BOX_OFFICE->value)
        ->assertJsonPath('data.customer.name', 'Buyer Name')
        ->assertJsonPath('data.customer.email', 'buyer@example.com')
        ->assertJsonPath('data.customer.phone', '07123456789')
        ->assertJsonPath('data.created_by.id', $user->id)
        ->assertJsonPath('data.payment.provider', PaymentProviderEnum::CARD_TERMINAL->value)
        ->assertJsonPath('data.payment.reference_name', 'SumUp Door 1')
        ->assertJsonPath('data.payment.external_reference_id', 'TXN-849201')
        ->assertJsonPath('data.items.0.ticket_name', 'General Admission')
        ->assertJsonPath('data.items.0.issued_tickets.0.code', $scanCode);
});

test('event order detail returns not found for another organisations order', function () {
    ['token' => $token] = eventOrdersOrgFixture();

    $otherOwner = User::factory()->create();
    $otherOrganisation = Organisation::factory()->create([
        'owner_id' => $otherOwner->id,
        'approve_status' => true,
    ]);
    $otherEvent = Event::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Org Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);
    $otherTicket = EventTicket::query()->create([
        'event_id' => $otherEvent->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'Other Ticket',
        'base_price' => 30.00,
        'currency' => 'GBP',
        'quantity_cap' => 50,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);
    ['order' => $otherOrder] = createPaidOrderForEvent(
        $otherOrganisation,
        $otherEvent,
        $otherTicket,
        'other@example.com',
    );

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/detail/'.$otherOrder->id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.order_not_found'));
});

test('event orders list can search by order unique_id customer fields ticket name and issued ticket unique_id', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    ['order' => $matchedOrder] = createPaidOrderForEvent(
        $organisation,
        $event,
        $ticket,
        'matched@example.com',
    );

    $matchedOrder->update([
        'customer_name' => 'Unique Search Buyer',
        'customer_phone' => '07999888777',
    ]);

    $matchedItem = OrderItem::query()->where('order_id', $matchedOrder->id)->firstOrFail();
    $matchedItem->update(['ticket_name' => 'VIP Search Pass']);

    IssuedTicket::query()
        ->where('order_id', $matchedOrder->id)
        ->update(['ticket_name' => 'VIP Search Pass']);

    createPaidOrderForEvent(
        $organisation,
        $event,
        $ticket,
        'other-buyer@example.com',
    );

    $issuedTicketUniqueId = IssuedTicket::query()
        ->where('order_id', $matchedOrder->id)
        ->value('unique_id');

    $searches = [
        $matchedOrder->unique_id,
        'Unique Search Buyer',
        'matched@example.com',
        '07999888777',
        'VIP Search Pass',
        $issuedTicketUniqueId,
    ];

    foreach ($searches as $search) {
        test()->withToken($token)
            ->getJson('/api/organisation/event/orders/'.$event->id.'?search='.urlencode((string) $search))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.unique_id', $matchedOrder->unique_id);
    }
});

test('event orders list search returns empty when nothing matches', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventOrdersOrgFixture();

    createPaidOrderForEvent($organisation, $event, $ticket);

    test()->withToken($token)
        ->getJson('/api/organisation/event/orders/'.$event->id.'?search=no-such-order')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(0, 'data.items');
});
