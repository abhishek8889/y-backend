<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
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
function refundsListOrgFixture(): array
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
        'name' => 'Refunds List Event',
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
 * @return array{order: Order, payment: Payment, refund: Refund}
 */
function createRefundForOrganisation(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    User $owner,
    string $customerEmail = 'buyer@example.com',
    RefundStatusEnum $status = RefundStatusEnum::SUCCEEDED,
    string $refundAmount = '20.00',
): array {
    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'status' => OrderStatusEnum::REFUNDED,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Refund Buyer',
        'customer_email' => $customerEmail,
        'customer_phone' => '07123456789',
        'paid_at' => now(),
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

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::CASH,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => '20.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);

    $refund = Refund::query()->create([
        'unique_id' => UniqueIdGenerator::generate('REF', 12),
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'requested_by' => $owner->id,
        'approved_by' => $owner->id,
        'amount' => $refundAmount,
        'currency' => 'GBP',
        'reason' => 'Customer cancellation',
        'status' => $status,
        'refunded_at' => $status === RefundStatusEnum::SUCCEEDED ? now() : null,
    ]);

    return [
        'order' => $order,
        'payment' => $payment,
        'refund' => $refund,
    ];
}

test('refunds list requires authentication', function () {
    test()->getJson('/api/organisation/orders/refunds/list')->assertUnauthorized();
});

test('organisation can list its refunds with order and payment details', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundsListOrgFixture();

    ['refund' => $refund, 'order' => $order] = createRefundForOrganisation(
        $organisation,
        $event,
        $ticket,
        $owner,
    );

    test()->withToken($token)
        ->getJson('/api/organisation/orders/refunds/list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_order_refunds_list'))
        ->assertJsonPath('data.items.0.unique_id', $refund->unique_id)
        ->assertJsonPath('data.items.0.status', RefundStatusEnum::SUCCEEDED->value)
        ->assertJsonPath('data.items.0.order.unique_id', $order->unique_id)
        ->assertJsonPath('data.items.0.order.customer_email', 'buyer@example.com')
        ->assertJsonPath('data.items.0.payment.provider', PaymentProviderEnum::CASH->value)
        ->assertJsonPath('data.items.0.requester.id', $owner->id)
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

test('organisation can filter refunds by status and search', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundsListOrgFixture();

    ['refund' => $succeeded] = createRefundForOrganisation(
        $organisation,
        $event,
        $ticket,
        $owner,
        'succeeded@example.com',
        RefundStatusEnum::SUCCEEDED,
    );

    createRefundForOrganisation(
        $organisation,
        $event,
        $ticket,
        $owner,
        'failed@example.com',
        RefundStatusEnum::FAILED,
        '5.00',
    );

    test()->withToken($token)
        ->getJson('/api/organisation/orders/refunds/list?status=succeeded&search='.urlencode($succeeded->unique_id))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.unique_id', $succeeded->unique_id);
});

test('organisation can view refund detail', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundsListOrgFixture();

    ['refund' => $refund, 'order' => $order] = createRefundForOrganisation(
        $organisation,
        $event,
        $ticket,
        $owner,
    );

    test()->withToken($token)
        ->getJson('/api/organisation/orders/refunds/detail/'.$refund->id)
        ->assertOk()
        ->assertJsonPath('message', __('messages.organisation_order_refund_details'))
        ->assertJsonPath('data.id', $refund->id)
        ->assertJsonPath('data.unique_id', $refund->unique_id)
        ->assertJsonPath('data.order.id', $order->id)
        ->assertJsonPath('data.approver.id', $owner->id);
});

test('refund detail returns not found for another organisation', function () {
    ['token' => $token] = refundsListOrgFixture();

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
        'base_price' => 10.00,
        'currency' => 'GBP',
        'quantity_cap' => 50,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    ['refund' => $otherRefund] = createRefundForOrganisation(
        $otherOrganisation,
        $otherEvent,
        $otherTicket,
        $otherOwner,
        'other@example.com',
    );

    test()->withToken($token)
        ->getJson('/api/organisation/orders/refunds/detail/'.$otherRefund->id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_order_refund_not_found'));
});
