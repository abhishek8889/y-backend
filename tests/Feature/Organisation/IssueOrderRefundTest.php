<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\OrganiserLedgerReferenceTypeEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Mail\AppMail;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganiserAccount;
use App\Models\OrganiserLedgerEntry;
use App\Models\OrganiserStaff;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\StripeService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

/**
 * @return array{
 *     owner: User,
 *     organisation: Organisation,
 *     event: Event,
 *     ticket: EventTicket,
 *     token: string
 * }
 */
function refundOrgFixture(): array
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
        'name' => 'Refund Event',
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
 * @return array{order: Order, payment: Payment, item: OrderItem, issued_ticket: IssuedTicket}
 */
function paidOrderForRefund(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    User $owner,
    PaymentProviderEnum $provider = PaymentProviderEnum::CASH,
    string $amount = '20.00',
    ?string $paymentIntentId = null,
): array {
    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'status' => OrderStatusEnum::PAID,
        'subtotal' => $amount,
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => $amount,
        'currency' => 'GBP',
        'customer_name' => 'Buyer Name',
        'customer_email' => 'buyer@example.com',
        'customer_phone' => '07123456789',
        'paid_at' => now(),
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => 1,
        'unit_price' => $amount,
        'discount_amount' => '0.00',
        'total_price' => $amount,
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    $issuedTicket = IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK'),
        'code' => 'YL'.strtoupper(Str::random(24)),
        'order_id' => $order->id,
        'order_item_id' => $item->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => $provider,
        'payment_intent_id' => $paymentIntentId,
        'status' => PaymentStatusEnum::SUCCEEDED,
        'amount' => $amount,
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
        'paid_at' => now(),
    ]);

    OrganiserAccount::query()->create([
        'organisation_id' => $organisation->id,
        'currency' => 'GBP',
        'total_earned' => '18.00',
        'total_paid' => '0.00',
        'total_reserved' => '18.00',
        'available_balance' => '0.00',
    ]);

    OrganiserLedgerEntry::query()->create([
        'organisation_id' => $organisation->id,
        'entry_type' => OrganiserLedgerEntryTypeEnum::EARNING,
        'reference_type' => OrganiserLedgerReferenceTypeEnum::ORDER_ITEM,
        'reference_id' => $item->id,
        'credit_amount' => '18.00',
        'debit_amount' => '0.00',
        'currency' => 'GBP',
        'idempotency_key' => 'earning_order_item_'.$item->id,
        'description' => 'Ticket sale',
    ]);

    return [
        'order' => $order,
        'payment' => $payment,
        'item' => $item,
        'issued_ticket' => $issuedTicket,
    ];
}

function mockStripeRefund(string $status = 'succeeded', ?int $amountMinor = 2000): void
{
    test()->mock(StripeService::class, function (MockInterface $mock) use ($status, $amountMinor): void {
        $mock->shouldReceive('createRefund')
            ->once()
            ->andReturn([
                'id' => 're_test_123',
                'status' => $status,
                'amount' => $amountMinor ?? 2000,
                'currency' => 'gbp',
                'payment_intent' => 'pi_test_refund_1',
            ]);
    });
}

function postStripeRefundWebhook(string $payload, string $secret = 'whsec_payment_test'): TestResponse
{
    $timestamp = time();
    $signedPayload = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return test()->call(
        'POST',
        route('stripe.payment-webhooks'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}",
        ],
        $payload,
    );
}

test('issue refund requires authentication', function () {
    test()->postJson('/api/organisation/orders/1/issue-refund', [
        'amount' => '10.00',
    ])->assertUnauthorized();
});

test('cash full refund succeeds without stripe and refunds tickets', function () {
    Mail::fake();

    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundOrgFixture();

    ['order' => $order, 'issued_ticket' => $issuedTicket] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::CASH,
    );

    test()->withToken($token)
        ->postJson('/api/organisation/orders/'.$order->id.'/issue-refund', [
            'amount' => '20.00',
            'reason' => 'Customer requested cancellation',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_order_refund_issued'))
        ->assertJsonPath('data.status', RefundStatusEnum::SUCCEEDED->value)
        ->assertJsonPath('data.amount', '20.00')
        ->assertJsonPath('data.reason', 'Customer requested cancellation')
        ->assertJsonPath('data.requested_by', $owner->id)
        ->assertJsonPath('data.approved_by', $owner->id)
        ->assertJsonPath('data.stripe_refund_id', null);

    expect($order->fresh()->status)->toBe(OrderStatusEnum::REFUNDED)
        ->and($issuedTicket->fresh()->status)->toBe(IssuedTicketStatusEnum::REFUNDED)
        ->and(OrganiserLedgerEntry::query()->where('entry_type', OrganiserLedgerEntryTypeEnum::REFUND)->count())->toBe(1);

    Mail::assertQueued(AppMail::class, function (AppMail $mail) use ($order, $ticket, $event): bool {
        return $mail->mailView === 'mail.order-refund'
            && ($mail->data['order_id'] ?? null) === $order->id
            && ($mail->data['order_unique_id'] ?? null) === $order->unique_id
            && ($mail->data['expected_days'] ?? null) === 10
            && ($mail->data['tickets'][0]['ticket_id'] ?? null) === $ticket->id
            && ($mail->data['tickets'][0]['ticket_name'] ?? null) === $ticket->name
            && ($mail->data['tickets'][0]['event_id'] ?? null) === $event->id
            && ($mail->data['tickets'][0]['event_name'] ?? null) === $event->name;
    });
});

test('card terminal partial refund does not void tickets', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundOrgFixture();

    ['order' => $order, 'issued_ticket' => $issuedTicket] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::CARD_TERMINAL,
    );

    test()->withToken($token)
        ->postJson('/api/organisation/orders/'.$order->id.'/issue-refund', [
            'amount' => '5.00',
            'reason' => 'Partial goodwill',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatusEnum::SUCCEEDED->value)
        ->assertJsonPath('data.amount', '5.00');

    expect($order->fresh()->status)->toBe(OrderStatusEnum::PARTIALLY_REFUNDED)
        ->and($issuedTicket->fresh()->status)->toBe(IssuedTicketStatusEnum::VALID);
});

test('stripe refund uses stripe service when payment intent exists', function () {
    mockStripeRefund('succeeded', 2000);

    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundOrgFixture();

    ['order' => $order] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::STRIPE,
        '20.00',
        'pi_test_refund_1',
    );

    test()->withToken($token)
        ->postJson('/api/organisation/orders/'.$order->id.'/issue-refund', [
            'amount' => '20.00',
            'reason' => 'Stripe full refund',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', RefundStatusEnum::SUCCEEDED->value)
        ->assertJsonPath('data.stripe_refund_id', 're_test_123');

    expect($order->fresh()->status)->toBe(OrderStatusEnum::REFUNDED);
});

test('refund amount exceeding remaining balance is rejected', function () {
    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = refundOrgFixture();

    ['order' => $order] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::CASH,
    );

    test()->withToken($token)
        ->postJson('/api/organisation/orders/'.$order->id.'/issue-refund', [
            'amount' => '25.00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', __('messages.organisation_order_refund_exceeds_remaining'));
});

test('refund for another organisations order is not found', function () {
    ['token' => $token] = refundOrgFixture();

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

    ['order' => $otherOrder] = paidOrderForRefund(
        $otherOrganisation,
        $otherEvent,
        $otherTicket,
        $otherOwner,
    );

    test()->withToken($token)
        ->postJson('/api/organisation/orders/'.$otherOrder->id.'/issue-refund', [
            'amount' => '10.00',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.order_not_found'));
});

test('refund.updated webhook marks processing refund as succeeded', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
    ] = refundOrgFixture();

    ['order' => $order, 'payment' => $payment, 'issued_ticket' => $issuedTicket] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::STRIPE,
        '20.00',
        'pi_test_webhook_refund',
    );

    $refund = Refund::query()->create([
        'unique_id' => UniqueIdGenerator::generate('REF', 12),
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'requested_by' => $owner->id,
        'approved_by' => $owner->id,
        'stripe_refund_id' => 're_webhook_1',
        'amount' => '20.00',
        'currency' => 'GBP',
        'reason' => 'Webhook settle',
        'status' => RefundStatusEnum::PROCESSING,
    ]);

    $payload = json_encode([
        'id' => 'evt_refund_updated_1',
        'type' => 'refund.updated',
        'data' => [
            'object' => [
                'id' => 're_webhook_1',
                'object' => 'refund',
                'amount' => 2000,
                'currency' => 'gbp',
                'status' => 'succeeded',
                'payment_intent' => 'pi_test_webhook_refund',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeRefundWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.type', 'refund.updated')
        ->assertJsonPath('data.handled', true);

    expect($refund->fresh()->status)->toBe(RefundStatusEnum::SUCCEEDED)
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::REFUNDED)
        ->and($issuedTicket->fresh()->status)->toBe(IssuedTicketStatusEnum::REFUNDED);
});

test('refund.failed webhook marks refund failed without changing order', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
    ] = refundOrgFixture();

    ['order' => $order, 'payment' => $payment, 'issued_ticket' => $issuedTicket] = paidOrderForRefund(
        $organisation,
        $event,
        $ticket,
        $owner,
        PaymentProviderEnum::STRIPE,
        '20.00',
        'pi_test_webhook_refund_fail',
    );

    $refund = Refund::query()->create([
        'unique_id' => UniqueIdGenerator::generate('REF', 12),
        'order_id' => $order->id,
        'payment_id' => $payment->id,
        'requested_by' => $owner->id,
        'approved_by' => $owner->id,
        'stripe_refund_id' => 're_webhook_fail',
        'amount' => '20.00',
        'currency' => 'GBP',
        'status' => RefundStatusEnum::PROCESSING,
    ]);

    $payload = json_encode([
        'id' => 'evt_refund_failed_1',
        'type' => 'refund.failed',
        'data' => [
            'object' => [
                'id' => 're_webhook_fail',
                'object' => 'refund',
                'amount' => 2000,
                'currency' => 'gbp',
                'status' => 'failed',
                'failure_reason' => 'insufficient_funds',
                'payment_intent' => 'pi_test_webhook_refund_fail',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeRefundWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    expect($refund->fresh()->status)->toBe(RefundStatusEnum::FAILED)
        ->and($refund->fresh()->failure_reason)->toBe('insufficient_funds')
        ->and($order->fresh()->status)->toBe(OrderStatusEnum::PAID)
        ->and($issuedTicket->fresh()->status)->toBe(IssuedTicketStatusEnum::VALID);
});
