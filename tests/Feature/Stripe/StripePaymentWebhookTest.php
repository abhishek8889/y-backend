<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\WebhookEventStatusEnum;
use App\Mail\AppMail;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganiserAccount;
use App\Models\OrganiserLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAddress;
use App\Models\WebhookEvent;
use App\Services\OrderConfirmationMailService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
    Mail::fake();

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

    Mail::assertQueued(AppMail::class, function (AppMail $mail) use ($order): bool {
        return $mail->hasTo('jane@example.com')
            && $mail->mailView === 'mail.order-confirmation'
            && str_contains($mail->mailSubject, 'Webhook Event')
            && $mail->data['order_unique_id'] === $order->unique_id
            && $mail->data['formatted_total'] === '£40.00'
            && count($mail->data['line_items']) === 1
            && $mail->data['line_items'][0]['offer_name'] === 'Early Bird'
            && $mail->data['line_items'][0]['quantity'] === 2
            && count($mail->fileAttachments) === 1
            && ($mail->fileAttachments[0]['mime'] ?? null) === 'application/pdf';
    });

    $order->refresh();
    expect($order->confirmation_email_sent_at)->not->toBeNull()
        ->and($order->tickets_pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($order->tickets_pdf_path))->toBeTrue();

    $issuedTickets = IssuedTicket::query()->where('order_id', $order->id)->get();
    expect($issuedTickets)->toHaveCount(2)
        ->and($issuedTickets->every(fn (IssuedTicket $ticket): bool => $ticket->status === IssuedTicketStatusEnum::VALID))->toBeTrue()
        ->and($issuedTickets->every(fn (IssuedTicket $ticket): bool => $ticket->offer_name === 'Early Bird'))->toBeTrue()
        ->and($issuedTickets->pluck('code')->unique())->toHaveCount(2);
});

test('payment_intent.succeeded is idempotent', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');
    Mail::fake();

    ['order' => $order] = pendingPaidOrderFixture();

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
        ->and(WebhookEvent::query()->count())->toBe(1)
        ->and(IssuedTicket::query()->where('order_id', $order->id)->count())->toBe(2);

    Mail::assertQueued(AppMail::class, 1);
});

test('marks payment failed on payment_intent.payment_failed', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');
    Mail::fake();

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

    Mail::assertNothingOutgoing();
});

test('cancels payment and pending order on payment_intent.canceled', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');
    Mail::fake();

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

    Mail::assertNothingOutgoing();
});

test('successful payment issues one ticket per purchased quantity across offers', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');
    Mail::fake();

    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $owner = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Multi Offer Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
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
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $earlyBird = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
        'price' => 20.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $senior = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Senior Citizen',
        'price' => 15.00,
        'quantity_cap' => 30,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 2,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => null,
        'status' => OrderStatusEnum::PENDING,
        'subtotal' => '125.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '12.50',
        'total_amount' => '125.00',
        'currency' => 'GBP',
        'customer_name' => 'Guest Buyer',
        'customer_email' => 'guest-tickets@example.com',
        'expires_at' => now()->addMinutes(15),
    ]);

    $earlyBirdItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $earlyBird->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $earlyBird->name,
        'quantity' => 4,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '80.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '8.00',
        'organiser_share_amount' => '72.00',
        'currency' => 'GBP',
    ]);

    $seniorItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $senior->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $senior->name,
        'quantity' => 3,
        'unit_price' => '15.00',
        'discount_amount' => '0.00',
        'total_price' => '45.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '4.50',
        'organiser_share_amount' => '40.50',
        'currency' => 'GBP',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'payment_intent_id' => 'pi_issue_tickets_1',
        'status' => PaymentStatusEnum::PROCESSING,
        'amount' => '125.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
    ]);

    $payload = json_encode([
        'id' => 'evt_pi_issue_tickets',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_issue_tickets_1',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'latest_charge' => 'ch_issue_1',
                'payment_method' => 'pm_card_visa',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    $issuedTickets = IssuedTicket::query()->where('order_id', $order->id)->get();

    expect($issuedTickets)->toHaveCount(7)
        ->and($issuedTickets->where('order_item_id', $earlyBirdItem->id))->toHaveCount(4)
        ->and($issuedTickets->where('order_item_id', $seniorItem->id))->toHaveCount(3)
        ->and($issuedTickets->where('offer_name', 'Early Bird'))->toHaveCount(4)
        ->and($issuedTickets->where('offer_name', 'Senior Citizen'))->toHaveCount(3)
        ->and($issuedTickets->pluck('code')->unique())->toHaveCount(7)
        ->and($issuedTickets->every(fn (IssuedTicket $ticket): bool => $ticket->ticket_name === 'General Admission'))->toBeTrue()
        ->and($issuedTickets->every(fn (IssuedTicket $ticket): bool => $ticket->status === IssuedTicketStatusEnum::VALID))->toBeTrue();
});

test('successful payment sends confirmation email and guest checkout uses order email', function () {
    Config::set('stripe.payment_webhook_secret', 'whsec_payment_test');
    Mail::fake();

    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $owner = User::factory()->create();

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'Royal Albert Hall',
        'status' => 'active',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    VenueAddress::query()->create([
        'venue_id' => $venue->id,
        'address_line_1' => 'Kensington Gore',
        'address_line_2' => null,
        'city' => 'London',
        'state' => 'Greater London',
        'postal_code' => 'SW7 2AP',
        'country' => 'United Kingdom',
    ]);

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Summer Gala',
        'venue_id' => $venue->id,
        'starts_at' => now()->addDays(10)->setTime(19, 0),
        'ends_at' => now()->addDays(10)->setTime(22, 0),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
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
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $earlyBird = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Bird',
        'price' => 20.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $vip = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'VIP',
        'price' => 50.00,
        'quantity_cap' => 20,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 2,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => null,
        'status' => OrderStatusEnum::PENDING,
        'subtotal' => '90.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '9.00',
        'total_amount' => '90.00',
        'currency' => 'GBP',
        'customer_name' => 'Guest Buyer',
        'customer_email' => 'guest@example.com',
        'expires_at' => now()->addMinutes(15),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $earlyBird->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $earlyBird->name,
        'quantity' => 2,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '40.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '4.00',
        'organiser_share_amount' => '36.00',
        'currency' => 'GBP',
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $vip->id,
        'ticket_name' => $ticket->name,
        'offer_name' => $vip->name,
        'quantity' => 1,
        'unit_price' => '50.00',
        'discount_amount' => '0.00',
        'total_price' => '50.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '5.00',
        'organiser_share_amount' => '45.00',
        'currency' => 'GBP',
    ]);

    Payment::query()->create([
        'order_id' => $order->id,
        'provider' => PaymentProviderEnum::STRIPE,
        'payment_intent_id' => 'pi_guest_multi_1',
        'status' => PaymentStatusEnum::PROCESSING,
        'amount' => '90.00',
        'currency' => 'GBP',
        'idempotency_key' => 'order_'.$order->unique_id.'_payment',
    ]);

    $payload = json_encode([
        'id' => 'evt_pi_guest_multi',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_guest_multi_1',
                'object' => 'payment_intent',
                'status' => 'succeeded',
                'latest_charge' => 'ch_guest_1',
                'payment_method' => 'pm_card_visa',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripePaymentWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    $order->refresh();
    expect($order->status)->toBe(OrderStatusEnum::PAID)
        ->and($order->confirmation_email_sent_at)->not->toBeNull();

    Mail::assertQueued(AppMail::class, function (AppMail $mail) use ($order, $organisation): bool {
        return $mail->hasTo('guest@example.com')
            && $mail->mailView === 'mail.order-confirmation'
            && str_contains($mail->mailSubject, 'Summer Gala')
            && $mail->data['formatted_total'] === '£90.00'
            && count($mail->data['line_items']) === 2
            && $mail->data['line_items'][0]['offer_name'] === 'Early Bird'
            && $mail->data['line_items'][1]['offer_name'] === 'VIP'
            && $mail->data['events'][0]['name'] === 'Summer Gala'
            && $mail->data['events'][0]['venue_name'] === 'Royal Albert Hall'
            && str_contains((string) $mail->data['events'][0]['venue_address'], 'Kensington Gore')
            && $mail->data['events'][0]['organisation_name'] === $organisation->name
            && $mail->data['order_unique_id'] === $order->unique_id
            && count($mail->fileAttachments) === 1
            && str_contains((string) ($mail->fileAttachments[0]['name'] ?? ''), $order->unique_id);
    });

    expect($order->tickets_pdf_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($order->tickets_pdf_path))->toBeTrue();

    Mail::fake();
    app(OrderConfirmationMailService::class)->queueAfterSuccessfulPayment($order->fresh());
    Mail::assertNothingOutgoing();
});
