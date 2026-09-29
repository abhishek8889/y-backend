<?php

use App\Enum\StripeOnboardingStatusEnum;
use App\Models\Organisation;
use App\Models\OrganisationStripeAccount;
use App\Models\OrganisationStripeExternalAccount;
use Illuminate\Support\Facades\Config;

function stripeAccountWebhookSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signedPayload = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

function postStripeWebhook(string $payload, string $secret = 'whsec_test_secret')
{
    return test()->call(
        'POST',
        route('stripe.account-webhooks'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Stripe-Signature' => stripeAccountWebhookSignature($payload, $secret),
        ],
        $payload,
    );
}

test('rejects account webhooks without a valid stripe signature', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $this->postJson(route('stripe.account-webhooks'), [
        'id' => 'evt_test',
        'type' => 'account.updated',
        'data' => ['object' => ['id' => 'acct_test']],
    ])->assertStatus(400)
        ->assertJsonPath('success', false);
});

test('updates all organisation_stripe_accounts fields from account.updated', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $organisation = Organisation::factory()->create();
    $stripeAccount = OrganisationStripeAccount::query()->create([
        'organisation_id' => $organisation->id,
        'stripe_account_id' => 'acct_webhook_test',
        'account_type' => 'express',
        'country' => 'GB',
        'default_currency' => 'gbp',
        'business_type' => 'company',
        'email' => 'old@example.com',
        'charges_enabled' => false,
        'payouts_enabled' => false,
        'details_submitted' => false,
        'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
    ]);

    $payload = json_encode([
        'id' => 'evt_account_updated',
        'object' => 'event',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_webhook_test',
                'object' => 'account',
                'email' => 'connected@example.com',
                'country' => 'ie',
                'default_currency' => 'EUR',
                'business_type' => 'individual',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
                'controller' => [
                    'stripe_dashboard' => ['type' => 'express'],
                ],
                'requirements' => [
                    'currently_due' => [],
                    'past_due' => [],
                    'disabled_reason' => null,
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.type', 'account.updated')
        ->assertJsonPath('data.handled', true);

    $stripeAccount->refresh();

    expect($stripeAccount->email)->toBe('connected@example.com')
        ->and($stripeAccount->country)->toBe('IE')
        ->and($stripeAccount->default_currency)->toBe('eur')
        ->and($stripeAccount->business_type)->toBe('individual')
        ->and($stripeAccount->charges_enabled)->toBeTrue()
        ->and($stripeAccount->payouts_enabled)->toBeTrue()
        ->and($stripeAccount->details_submitted)->toBeTrue()
        ->and($stripeAccount->onboarding_status)->toBe(StripeOnboardingStatusEnum::COMPLETE)
        ->and($stripeAccount->onboarding_completed_at)->not->toBeNull()
        ->and($stripeAccount->last_synced_at)->not->toBeNull()
        ->and($stripeAccount->requirements_currently_due)->toBe([])
        ->and($stripeAccount->requirements_past_due)->toBe([])
        ->and($stripeAccount->requirements_disabled_reason)->toBeNull();
});

test('upserts organisation_stripe_external_accounts from external_account.created', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $organisation = Organisation::factory()->create();
    $stripeAccount = OrganisationStripeAccount::query()->create([
        'organisation_id' => $organisation->id,
        'stripe_account_id' => 'acct_ext_test',
        'account_type' => 'express',
        'country' => 'GB',
        'default_currency' => 'gbp',
        'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
    ]);

    $payload = json_encode([
        'id' => 'evt_ext_created',
        'object' => 'event',
        'type' => 'account.external_account.created',
        'data' => [
            'object' => [
                'id' => 'ba_test_123',
                'object' => 'bank_account',
                'account' => 'acct_ext_test',
                'bank_name' => 'STRIPE TEST BANK',
                'last4' => '6789',
                'country' => 'gb',
                'currency' => 'GBP',
                'status' => 'new',
                'default_for_currency' => true,
                'fingerprint' => 'fp_test',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', true);

    $external = OrganisationStripeExternalAccount::query()
        ->where('stripe_external_account_id', 'ba_test_123')
        ->first();

    expect($external)->not->toBeNull()
        ->and($external->organisation_stripe_account_id)->toBe($stripeAccount->id)
        ->and($external->object)->toBe('bank_account')
        ->and($external->bank_name)->toBe('STRIPE TEST BANK')
        ->and($external->last4)->toBe('6789')
        ->and($external->country)->toBe('GB')
        ->and($external->currency)->toBe('gbp')
        ->and($external->status)->toBe('new')
        ->and($external->is_default_for_currency)->toBeTrue()
        ->and($external->fingerprint)->toBe('fp_test');
});

test('updates charges_enabled from capability.updated', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $organisation = Organisation::factory()->create();
    $stripeAccount = OrganisationStripeAccount::query()->create([
        'organisation_id' => $organisation->id,
        'stripe_account_id' => 'acct_cap_test',
        'account_type' => 'express',
        'country' => 'GB',
        'default_currency' => 'gbp',
        'charges_enabled' => false,
        'details_submitted' => true,
        'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
    ]);

    $payload = json_encode([
        'id' => 'evt_cap',
        'object' => 'event',
        'type' => 'capability.updated',
        'data' => [
            'object' => [
                'id' => 'card_payments',
                'object' => 'capability',
                'account' => 'acct_cap_test',
                'status' => 'active',
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)->assertOk()->assertJsonPath('data.handled', true);

    $stripeAccount->refresh();

    expect($stripeAccount->charges_enabled)->toBeTrue()
        ->and($stripeAccount->onboarding_status)->toBe(StripeOnboardingStatusEnum::COMPLETE);
});

test('accepts v2 thin account webhooks for unknown accounts without crashing', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $payload = json_encode([
        'id' => 'evt_v2_unknown_account',
        'object' => 'v2.core.event',
        'type' => 'v2.core.account[requirements].updated',
        'created' => '2025-04-28T20:33:01.123Z',
        'livemode' => false,
        'related_object' => [
            'id' => 'acct_unknown_v2',
            'type' => 'v2.core.account',
            'url' => '/v2/core/accounts/acct_unknown_v2',
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.type', 'v2.core.account[requirements].updated')
        ->assertJsonPath('data.version', 'v2')
        ->assertJsonPath('data.handled', false);
});

test('ignores v2 account_person events', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $payload = json_encode([
        'id' => 'evt_person',
        'object' => 'v2.core.event',
        'type' => 'v2.core.account_person.updated',
        'created' => '2025-04-28T20:33:01.123Z',
        'livemode' => false,
        'related_object' => [
            'id' => 'person_test',
            'type' => 'v2.core.account_person',
            'url' => '/v2/core/accounts/acct_x/persons/person_test',
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', false);
});

test('ignores account webhooks for unknown stripe accounts', function () {
    Config::set('stripe.webhook_secret', 'whsec_test_secret');

    $payload = json_encode([
        'id' => 'evt_unknown_account',
        'object' => 'event',
        'type' => 'account.updated',
        'data' => [
            'object' => [
                'id' => 'acct_unknown',
                'object' => 'account',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    postStripeWebhook($payload)
        ->assertOk()
        ->assertJsonPath('data.handled', false);
});
