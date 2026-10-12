<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\WebhookEventStatusEnum;
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
use App\Models\WebhookEvent;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function stripePaymentWebhookSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signedPayload = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

function postStripePaymentWebhook(string $payload, string $secret = 'whsec_payment_test'): TestResponse
{
    return test()->call(
        'POST',
        route('stripe.payment-webhooks'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => stripePaymentWebhookSignature($payload, $secret),
        ],
        $payload,
    );
}

/**
 * @return array{order: Order, payment: Payment, organisation: Organisation}
 */
function pendingPaidOrderFixture(): array
{
    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Webhook Event',
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

    $offer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
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
        'status' => OrderStatusEnum::PENDING,
        'subtotal' => '40.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '4.00',
        'total_amount' => '40.00',
        'currency' => 'GBP',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'expires_at' => now()->addMinutes(15),
    ]);

    OrderItem::query()->create([
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

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'payment_intent_id' => 'pi_test_webhook_1',
        'status' => PaymentStatusEnum::PROCESSING,
        'amount' => '40.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
    ]);

    return [
        'order' => $order,
        'payment' => $payment,
        'organisation' => $organisation,
    ];
}

test('rejects payment webhooks without a valid stripe signature', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    $this->postJson(route('stripe.payment-webhooks'), [
        'id' => 'evt_test',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test']],
    ])->assertStatus(400)
        ->assertJsonPath('success', false);
});

test('marks payment and order paid on payment_intent.succeeded', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    ['order' => $order, 'payment' => $payment, 'organisation' => $organisation] = pendingPaidOrderFixture();

    $payload = json_encode([
        'id' => 'evt_pi_succeeded',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook_1',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'latest_charge' => 'ch_test_123',
                'payment_method' => 'pm_card_visa',
                'amount' => 4000,
                'currency' => 'gbp',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.type', 'payment_intent.succeeded')
        ->assertJsonPath('data.handled', true);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatusEnum::SUCCEEDED)
        ->and($payment->charge_id)->toBe('ch_test_123')
        ->and($payment->payment_method)->toBe('pm_card_visa')
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatusEnum::PAID)
        ->and($order->paid_at)->not->toBeNull();

    $ledger = OrganiserLedgerEntry::query()->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->organisation_id)->toBe($organisation->id)
        ->and($ledger->entry_type)->toBe(OrganiserLedgerEntryTypeEnum::EARNING)
        ->and((string) $ledger->credit_amount)->toBe('36.00');

    $account = OrganiserAccount::query()->first();
    expect($account)->not->toBeNull()
        ->and((string) $account->total_earned)->toBe('36.00')
        ->and((string) $account->total_reserved)->toBe('36.00')
        ->and((string) $account->available_balance)->toBe('0.00');

    $webhookEvent = WebhookEvent::query()->first();
    expect($webhookEvent)->not->toBeNull()
        ->and($webhookEvent->status)->toBe(WebhookEventStatusEnum::PROCESSED)
        ->and($webhookEvent->event_id)->toBe('evt_pi_succeeded');
});

test('payment_intent.succeeded is idempotent', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    pendingPaidOrderFixture();

    $payload = json_encode([
        'id' => 'evt_pi_succeeded_once',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook_1',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'latest_charge' => 'ch_test_123',
                'payment_method' => 'pm_card_visa',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)->assertOk();
    postStripePaymentWebhook($payload)->assertOk()->assertJsonPath('data.handled', true);

    expect(OrganiserLedgerEntry::query()->count())->toBe(1)
        ->and(WebhookEvent::query()->count())->toBe(1);
});

test('marks payment failed on payment_intent.payment_failed', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    ['order' => $order, 'payment' => $payment] = pendingPaidOrderFixture();

    $payload = json_encode([
        'id' => 'evt_pi_failed',
        'object' => 'event',
        'type' => 'payment_intent.payment_failed',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook_1',
                'object' => 'payment_intent',
                'status' => 'requires_payment_method',
                'last_payment_error' => [
                    'message' => 'Your card was declined.',
                    'code' => 'card_declined',
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatusEnum::FAILED)
        ->and($payment->failure_reason)->toBe('Your card was declined.')
        ->and($order->status)->toBe(OrderStatusEnum::PENDING);
});

test('cancels payment and pending order on payment_intent.canceled', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');

    ['order' => $order, 'payment' => $payment] = pendingPaidOrderFixture();

    $payload = json_encode([
        'id' => 'evt_pi_canceled',
        'object' => 'event',
        'type' => 'payment_intent.canceled',
        'data' => [
            'object' => [
                'id' => 'pi_test_webhook_1',
                'object' => 'payment_intent',
                'status' => 'canceled',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe(PaymentStatusEnum::CANCELLED)
        ->and($order->status)->toBe(OrderStatusEnum::CANCELLED)
        ->and($order->cancelled_at)->not->toBeNull();
});
