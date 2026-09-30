<?php

namespace App\Services;

use App\Enum\StripeOnboardingStatusEnum;
use App\Models\Organisation;
use App\Models\OrganisationStripeAccount;
use App\Models\User;
use App\Services\Organisation\OrganisationService;
use App\Services\Stripe\StripeClient;
use Illuminate\Support\Carbon;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe business logic for organisation Connect APIs.
 */
class StripeService extends Service
{
    public const ACCOUNT_LINK_ONBOARDING = 'account_onboarding';

    public function __construct(private OrganisationService $organisation) {}

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
        $currency = strtolower((string) config('stripe.connect.default_currency', 'gbp'));
        $entityType = (string) ($options['business_type']
            ?? config('stripe.connect.business_type', 'company'));
        $dashboard = (string) config('stripe.connect.account_type', 'express');

        try {
            $account = $this->stripe()->request('post', '/v2/core/accounts', [
                'contact_email' => $email,
                'display_name' => $organisation->name,
                'dashboard' => $dashboard,
                'identity' => [
                    'country' => $country,
                    // 'entity_type' => $entityType,
                    // 'business_details' => [
                    //     'registered_name' => $organisation->name,
                    // ],
                ],
                'configuration' => [
                    'merchant' => [
                        'capabilities' => [
                            'card_payments' => ['requested' => true],
                        ],
                    ],
                ],
                'defaults' => [
                    'currency' => $currency,
                    'responsibilities' => [
                        'fees_collector' => 'application',
                        'losses_collector' => 'application',
                    ],
                ],
                'include' => [
                    'configuration.merchant',
                    'identity',
                    'defaults',
                    'requirements',
                ],
                'metadata' => [
                    'organisation_id' => (string) $organisation->id,
                    'organisation_unique_id' => (string) $organisation->unique_id,
                ],
            ], []);
        } catch (ApiErrorException $exception) {
            $this->fail(
                Response::HTTP_BAD_GATEWAY,
                __('messages.stripe_api_error'),
                $exception->getMessage(),
            );
        }

        $stripeAccount = OrganisationStripeAccount::query()->create([
            'organisation_id' => $organisation->id,
            'stripe_account_id' => $account->id,
            'account_type' => $dashboard,
            'country' => $country,
            'default_currency' => $currency,
            'business_type' => $entityType,
            'email' => $email,
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'details_submitted' => false,
            'onboarding_status' => StripeOnboardingStatusEnum::PENDING,
            'last_synced_at' => Carbon::now(),
            'metadata' => [
                'stripe_created' => $account->created ?? null,
                'api' => 'v2',
            ],
        ]);

        $onboardingLink = $this->createAccountOnboardingLink($stripeAccount, $options);

        return [
            'stripe_account' => $stripeAccount,
            'onboarding_link' => $onboardingLink,
        ];
    }

    /**
     * Create an Account Link for onboarding a connected account.
     *
     * @param  array<string, mixed>  $options
     * @return array{url: string, expires_at: string|null}
     */
    public function createAccountOnboardingLink(
        OrganisationStripeAccount $stripeAccount,
        array $options = [],
    ): array {
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
                'type' => self::ACCOUNT_LINK_ONBOARDING,
                'collection_options' => [
                    'fields' => 'eventually_due', // collect all fields including bank account
                ],
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
     * Resolve the organisation Stripe account for the authenticated user context.
     *
     * @return array{stripe_account: OrganisationStripeAccount}
     */
    public function resolveOrganisationStripeAccount(User $user): array
    {
        $organisation = $this->organisation->getOrganisationDetail($user)['organisation'];

        $stripeAccount = $organisation->stripeAccount;
        if ($stripeAccount === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.stripe_connected_account_not_found'),
            );
        }

        // $stripeAccountDetails = $this->stripe()->accounts->retrieve($stripeAccount->stripe_account_id);
        return [
            'stripe_account' => $stripeAccount,
        ];
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
}
