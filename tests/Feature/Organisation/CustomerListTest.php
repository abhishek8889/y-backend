<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
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
function customerListOrgFixture(): array
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
        'name' => 'Customer List Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
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
function attachOrganisationCustomer(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    ?User $customer = null,
    StatusEnum $status = StatusEnum::ACTIVE,
    string $firstName = 'Ada',
    string $lastName = 'Lovelace',
    string $email = 'ada@example.com',
    string $totalAmount = '20.00',
    int $quantity = 1,
): array {
    $customer ??= User::factory()->create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => '07111111111',
        'country_code' => '+44',
        'country' => 'GB',
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => $totalAmount,
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => $totalAmount,
        'currency' => 'GBP',
        'customer_name' => trim($customer->first_name.' '.$customer->last_name),
        'customer_email' => $customer->email,
        'customer_phone' => $customer->phone,
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => $quantity,
        'unit_price' => $totalAmount,
        'discount_amount' => '0.00',
        'total_price' => $totalAmount,
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    $membership = OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
        'first_order_id' => $order->id,
        'last_order_id' => $order->id,
        'status' => $status,
    ]);

    return [
        'customer' => $customer,
        'membership' => $membership,
        'order' => $order,
    ];
}

test('customer list requires authentication', function () {
    test()->getJson('/api/organisation/customer/list')->assertUnauthorized();
});

test('organisation can list customers linked to itself', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerListOrgFixture();

    ['customer' => $customer, 'membership' => $membership, 'order' => $order] = attachOrganisationCustomer(
        $organisation,
        $event,
        $ticket,
    );

    // Second paid order for the same customer (membership already exists).
    $secondOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '15.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '1.50',
        'total_amount' => '15.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);
    OrderItem::query()->create([
        'order_id' => $secondOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => 1,
        'unit_price' => '15.00',
        'discount_amount' => '0.00',
        'total_price' => '15.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '1.50',
        'organiser_share_amount' => '13.50',
        'currency' => 'GBP',
    ]);
    $membership->update(['last_order_id' => $secondOrder->id]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::CASH,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '20.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);
    Refund::query()->create([
        'unique_id' => UniqueIdGenerator::generate('REF', 12),
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'requested_by' => $owner->id,
        'approved_by' => $owner->id,
        'amount' => '10.00',
        'currency' => 'GBP',
        'reason' => 'Partial refund',
        'status' => RefundStatusEnum::SUCCEEDED,
        'refunded_at' => now(),
    ]);
    $order->update(['status' => OrderStatusEnum::PARTIALLY_REFUNDED]);

    // Complimentary ticket must not inflate total_spent (even if a face value is stored).
    OrderItem::query()->create([
        'order_id' => $secondOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => 1,
        'pricing_type' => OrderItemPricingTypeEnum::COMPLIMENTARY,
        'unit_price' => '50.00',
        'discount_amount' => '0.00',
        'total_price' => '50.00',
        'platform_fee_rate' => '0.00',
        'platform_share_amount' => '0.00',
        'organiser_share_amount' => '0.00',
        'currency' => 'GBP',
    ]);

    // Customer belonging to another organisation must not appear.
    $otherOwner = User::factory()->create();
    $otherOrganisation = Organisation::factory()->create([
        'owner_id' => $otherOwner->id,
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
        'base_price' => 10.00,
        'currency' => 'GBP',
        'quantity_cap' => 50,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);
    attachOrganisationCustomer(
        $otherOrganisation,
        $otherEvent,
        $otherTicket,
        null,
        StatusEnum::ACTIVE,
        'Other',
        'Buyer',
        'other-buyer@example.com',
    );

    test()->withToken($token)
        ->getJson('/api/organisation/customer/list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_customer_list'))
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $customer->id)
        ->assertJsonPath('data.items.0.membership_id', $membership->id)
        ->assertJsonPath('data.items.0.first_name', 'Ada')
        ->assertJsonPath('data.items.0.last_name', 'Lovelace')
        ->assertJsonPath('data.items.0.name', 'Ada Lovelace')
        ->assertJsonPath('data.items.0.email', 'ada@example.com')
        ->assertJsonPath('data.items.0.phone', '07111111111')
        ->assertJsonPath('data.items.0.status', StatusEnum::ACTIVE->value)
        ->assertJsonPath('data.items.0.total_orders', 2)
        ->assertJsonPath('data.items.0.total_spent', '35.00')
        ->assertJsonPath('data.items.0.formatted_total_spent', '£35.00')
        ->assertJsonPath('data.items.0.total_refunded', '10.00')
        ->assertJsonPath('data.items.0.formatted_total_refunded', '£10.00')
        ->assertJsonPath('data.items.0.currency', 'GBP')
        ->assertJsonPath('data.items.0.last_order.id', $secondOrder->id)
        ->assertJsonPath('data.items.0.last_order.unique_id', $secondOrder->unique_id)
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'membership_id',
                        'first_name',
                        'last_name',
                        'name',
                        'email',
                        'phone',
                        'country_code',
                        'country',
                        'profile_image',
                        'status',
                        'total_orders',
                        'total_spent',
                        'formatted_total_spent',
                        'total_refunded',
                        'formatted_total_refunded',
                        'currency',
                        'last_order',
                        'customer_since',
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

test('customer list can filter by status and search', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerListOrgFixture();

    ['customer' => $active] = attachOrganisationCustomer(
        $organisation,
        $event,
        $ticket,
        null,
        StatusEnum::ACTIVE,
        'Ada',
        'Lovelace',
        'ada@example.com',
    );

    attachOrganisationCustomer(
        $organisation,
        $event,
        $ticket,
        null,
        StatusEnum::INACTIVE,
        'Grace',
        'Hopper',
        'grace@example.com',
    );

    test()->withToken($token)
        ->getJson('/api/organisation/customer/list?status='.StatusEnum::ACTIVE->value)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $active->id);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/list?search='.urlencode('Ada Lovelace'))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $active->id);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/list?search=grace@example.com')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.email', 'grace@example.com');
});

test('customer list rejects an invalid status', function () {
    ['token' => $token] = customerListOrgFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/customer/list?status=not-a-status')
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.organisation_customer_invalid_status'));
});
