<?php

namespace App\Services;

use App\Enum\StripeOnboardingStatusEnum;
use App\Models\Organisation;
use App\Models\OrganisationStripeAccount;
use App\Models\OrganisationStripeExternalAccount;
use App\Models\User;
use App\Services\Stripe\StripeClient;
use Illuminate\Support\Carbon;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe business logic. Call Stripe APIs via StripeClient::get().
 */
class StripeService extends Service
{
    public const ACCOUNT_LINK_ONBOARDING = 'account_onboarding';

    public const ACCOUNT_LINK_UPDATE = 'account_update';

    /**
     * Create a Stripe Connected Account for an organisation and return an onboarding link.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     stripe_account: OrganisationStripeAccount,
     *     onboarding_link: array{url: string, expires_at: string|null}
     * }
     */
    public function createConnectedAccount(Organisation $organisation, array $options = []): array
    {
        if ($organisation->stripeAccount()->exists()) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.stripe_connected_account_already_exists'),
            );
        }

        $email = $organisation->email;
        if (! filled($email)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.stripe_organisation_email_required'),
            );
        }

        $country = $this->resolveConnectCountry($organisation, $options);
        // $businessType = (string) ($options['business_type']
        //     ?? config('stripe.connect.business_type', 'company'));

        try {
            $account = $this->stripe()->accounts->create([
                'country' => $country,
                'email' => $email,
                // 'business_type' => $businessType,
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'controller' => [
                    'losses' => ['payments' => 'application'],
                    'fees' => ['payer' => 'application'],
                    'requirement_collection' => 'stripe',
                    'stripe_dashboard' => ['type' => 'express'],
                ],
                'settings' => [
                    'payouts' => [
                        'schedule' => [
                            'interval' => 'manual',
                        ],
                        'debit_negative_balances' => true,
                    ],
                ],
                'metadata' => [
                    'organisation_id' => (string) $organisation->id,
                    'organisation_unique_id' => $organisation->unique_id,
                ],
            ]);

            dd('account created', $account);
        } catch (ApiErrorException $exception) {
            dd($exception->getMessage());
            $this->fail(
                Response::HTTP_BAD_GATEWAY,
                __('messages.stripe_api_error'),
                $exception->getMessage(),
            );
        }

        $stripeAccount = OrganisationStripeAccount::query()->create([
            'organisation_id' => $organisation->id,
            'stripe_account_id' => $account->id,
            'account_type' => (string) config('stripe.connect.account_type', 'custom'),
            'country' => $country,
            'default_currency' => strtolower((string) config('stripe.connect.default_currency', 'gbp')),
            'business_type' => filled($account->business_type ?? null)
                ? (string) $account->business_type
                : null,
            'email' => $email,
            'charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
            'details_submitted' => (bool) ($account->details_submitted ?? false),
            'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
            'last_synced_at' => Carbon::now(),
            'metadata' => [
                'stripe_created' => $account->created ?? null,
            ],
        ]);

        $onboardingLink = $this->createAccountLink(
            $stripeAccount,
            self::ACCOUNT_LINK_ONBOARDING,
            $options,
        );

        return [
            'stripe_account' => $stripeAccount,
            'onboarding_link' => $onboardingLink,
        ];
    }

    /**
     * Create an Account Link for onboarding a new connected account.
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, expires_at: string|null}
     */
    public function createAccountOnboardingLink(
        OrganisationStripeAccount $stripeAccount,
        array $options = [],
    ): array {
        return $this->createAccountLink(
            $stripeAccount,
            self::ACCOUNT_LINK_ONBOARDING,
            $options,
        );
    }

    /**
     * Create an Account Link for updating an existing connected account.
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, expires_at: string|null}
     */
    public function createAccountUpdateLink(
        OrganisationStripeAccount $stripeAccount,
        array $options = [],
    ): array {
        return $this->createAccountLink(
            $stripeAccount,
            self::ACCOUNT_LINK_UPDATE,
            $options,
        );
    }

    /**
     * Shared Account Link creator.
     * $type: account_onboarding (new) | account_update (existing).
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, expires_at: string|null}
     */
    public function createAccountLink(
        OrganisationStripeAccount $stripeAccount,
        string $type = self::ACCOUNT_LINK_ONBOARDING,
        array $options = [],
    ): array {
        if (! in_array($type, [self::ACCOUNT_LINK_ONBOARDING, self::ACCOUNT_LINK_UPDATE], true)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.stripe_invalid_account_link_type'),
            );
        }

        $returnUrl = (string) ($options['return_url']
            ?? config('stripe.connect.return_url')
            ?? rtrim((string) config('app.url'), '/').'/stripe/connect/return');

        $refreshUrl = (string) ($options['refresh_url']
            ?? config('stripe.connect.refresh_url')
            ?? rtrim((string) config('app.url'), '/').'/stripe/connect/refresh');

        try {
            $accountLink = $this->stripe()->accountLinks->create([
                'account' => $stripeAccount->stripe_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => $type,
            ]);
        } catch (ApiErrorException $exception) {
            $this->fail(
                Response::HTTP_BAD_GATEWAY,
                __('messages.stripe_api_error'),
                $exception->getMessage(),
            );
        }

        $expiresAt = isset($accountLink->expires_at)
            ? Carbon::createFromTimestamp($accountLink->expires_at)->toIso8601String()
            : null;

        return [
            'url' => $accountLink->url,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Sync connected-account capabilities and requirements from Stripe into local DB.
     *
     * @return array{stripe_account: OrganisationStripeAccount}
     */
    public function syncConnectedAccount(OrganisationStripeAccount $stripeAccount): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Sync external bank accounts for a connected account into local DB.
     *
     * @return array{external_accounts: list<OrganisationStripeExternalAccount>}
     */
    public function syncExternalAccounts(OrganisationStripeAccount $stripeAccount): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Retrieve a connected account from Stripe (no local persist).
     *
     * @return array<string, mixed>
     */
    public function retrieveConnectedAccount(string $stripeAccountId): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Create a PaymentIntent on the platform account for a ticket purchase.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPaymentIntent(Organisation $organisation, array $payload): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Retrieve a PaymentIntent from Stripe.
     *
     * @return array<string, mixed>
     */
    public function retrievePaymentIntent(string $paymentIntentId): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Transfer funds from the platform balance to a connected account.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTransfer(OrganisationStripeAccount $stripeAccount, array $payload): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Refund a platform charge / PaymentIntent.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRefund(string $paymentIntentId, array $payload = []): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Verify and parse an incoming Stripe webhook payload.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Handle a verified Stripe webhook event (routing only; no logic yet).
     *
     * @param  array{type: string, data: array<string, mixed>}  $event
     * @return array{handled: bool}
     */
    public function handleWebhookEvent(array $event): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Resolve the organisation Stripe account for the authenticated user context.
     *
     * @return array{stripe_account: OrganisationStripeAccount}
     */
    public function resolveOrganisationStripeAccount(User $user): array
    {
        $this->notImplemented(__FUNCTION__);
    }

    /**
     * Official Stripe SDK client (secret key already applied).
     */
    protected function stripe(): \Stripe\StripeClient
    {
        return StripeClient::get();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveConnectCountry(Organisation $organisation, array $options): string
    {
        $country = $options['country']
            ?? $organisation->country_code
            ?? config('stripe.connect.country', 'GB');

        $country = strtoupper(trim((string) $country));

        if (strlen($country) !== 2) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.stripe_invalid_country'),
            );
        }

        return $country;
    }

    private function notImplemented(string $method): never
    {
        $this->fail(
            Response::HTTP_NOT_IMPLEMENTED,
            __('messages.stripe_not_implemented', ['method' => $method]),
        );
    }
}
