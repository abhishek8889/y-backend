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
function eventRefundsListFixture(): array
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
        'name' => 'Event Refunds List',
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
function createEventRefund(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    User $owner,
    string $customerName = 'Refund Buyer',
    string $customerEmail = 'buyer@example.com',
    RefundStatusEnum $status = RefundStatusEnum::SUCCEEDED,
    string $refundAmount = '20.00',
    ?Order $existingOrder = null,
): array {
    if ($existingOrder !== null) {
        $order = $existingOrder;
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatusEnum::SUCCEEDED)
            ->orderByDesc('id')
            ->firstOrFail();
    } else {
        $order = Order::query()->create([
            'unique_id' => UniqueIdGenerator::generate('ORD', 12),
            'checkout_key' => (string) Str::uuid(),
            'status' => OrderStatusEnum::REFUNDED,
            'subtotal' => '20.00',
            'discount_amount' => '0.00',
            'platform_fee_amount' => '2.00',
            'total_amount' => '20.00',
            'currency' => 'GBP',
            'customer_name' => $customerName,
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
    }

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

test('event refunds list requires authentication', function () {
    test()->getJson('/api/organisation/event/1/refunds')->assertUnauthorized();
});

test('organisation can list orders that have refunds for its event', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventRefundsListFixture();

    ['order' => $order, 'refund' => $firstRefund] = createEventRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
    );

    ['refund' => $secondRefund] = createEventRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        'Refund Buyer',
        'buyer@example.com',
        RefundStatusEnum::SUCCEEDED,
        '5.00',
        $order,
    );

    // Another order with a refund should appear as a separate list item.
    createEventRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        'Second Buyer',
        'second@example.com',
    );

    $response = test()->withToken($token)
        ->getJson('/api/organisation/event/'.$event->id.'/refunds')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.event_refunds_list'))
        ->assertJsonCount(2, 'data.items')
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'unique_id',
                        'customer_name',
                        'total_refunded',
                        'refunds',
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

    $orderRow = collect($response->json('data.items'))
        ->firstWhere('unique_id', $order->unique_id);

    expect($orderRow)->not->toBeNull()
        ->and($orderRow['customer_name'])->toBe('Refund Buyer')
        ->and($orderRow['total_refunded'])->toBe('25.00')
        ->and($orderRow['refunds'])->toHaveCount(2)
        ->and(collect($orderRow['refunds'])->pluck('unique_id')->all())
        ->toContain($firstRefund->unique_id, $secondRefund->unique_id);
});

test('event refunds list can filter by status search and utc date range', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = eventRefundsListFixture();

    Carbon::setTestNow(Carbon::parse('2026-10-01 12:00:00', 'UTC'));
    ['refund' => $matched, 'order' => $matchedOrder] = createEventRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        'Ada Lovelace',
        'ada@example.com',
        RefundStatusEnum::SUCCEEDED,
        '15.50',
    );

    Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'UTC'));
    createEventRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        'Other Buyer',
        'other@example.com',
        RefundStatusEnum::FAILED,
        '5.00',
    );

    Carbon::setTestNow();

    $query = http_build_query([
        'status' => RefundStatusEnum::SUCCEEDED->value,
        'search' => $matchedOrder->unique_id,
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-02',
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/event/'.$event->id.'/refunds?'.$query)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.unique_id', $matchedOrder->unique_id)
        ->assertJsonPath('data.items.0.refunds.0.unique_id', $matched->unique_id);

    test()->withToken($token)
        ->getJson('/api/organisation/event/'.$event->id.'/refunds?search='.urlencode('Ada Lovelace'))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.unique_id', $matchedOrder->unique_id);

    test()->withToken($token)
        ->getJson('/api/organisation/event/'.$event->id.'/refunds?search=15.50')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.unique_id', $matchedOrder->unique_id);
});

test('event refunds list returns not found for another organisations event', function () {
    ['token' => $token] = eventRefundsListFixture();

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

    test()->withToken($token)
        ->getJson('/api/organisation/event/'.$otherEvent->id.'/refunds')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.event_not_found'));
});

test('event refunds list rejects an invalid date range', function () {
    [
        'event' => $event,
        'token' => $token,
    ] = eventRefundsListFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/event/'.$event->id.'/refunds?start_date=2026-10-20&end_date=2026-10-01')
        ->assertUnprocessable()
        ->assertJsonPath('message', __('messages.event_refunds_invalid_date'));
});
