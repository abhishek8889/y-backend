<?php

namespace App\Http\Controllers\Api\Webhooks\Stripe;

use App\Enum\StripeOnboardingStatusEnum;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\OrganisationStripeAccount;
use App\Models\OrganisationStripeExternalAccount;
use App\Services\Stripe\StripeClient;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\V2\Core\EventNotification;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe Connect account webhooks (POST /api/account-webhooks).
 *
 * Handled events:
 * - account.updated
 * - account.application.authorized
 * - account.application.deauthorized
 * - account.external_account.created / updated / deleted
 * - capability.updated
 * - v2.core.account.* and v2.core.account[*].* (not account_person)
 */
class StripeAccountWebhook extends Controller
{
    private const V2_ACCOUNT_INCLUDES = [
        'configuration.merchant',
        'identity',
        'defaults',
        'requirements',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->parseEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );

            Log::channel('stripe_webhook')->info('🚀############### Webhook received ###############🚀', [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'version' => $event['version'],
            ]);

            $handled = $this->dispatch($event);

            Log::channel('stripe_webhook')->info('Webhook handled', [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'version' => $event['version'],
                'handled' => $handled,
            ]);

            return $this->success(__('messages.stripe_webhook_received'), [
                'type' => $event['type'],
                'version' => $event['version'],
                'handled' => $handled,
            ]);
        } catch (Throwable $exception) {
            Log::channel('stripe_webhook')->error('Webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failed($exception);
        }
    }

    /**
     * @param  array{
     *     id: string,
     *     type: string,
     *     version: 'v1'|'v2',
     *     data: array<string, mixed>,
     *     notification: EventNotification|null
     * }  $event
     */
    private function dispatch(array $event): bool
    {
        return match (true) {
            in_array($event['type'], ['account.updated', 'account.application.authorized'], true) => $this->syncAccountFromV1Object($event['data']),

            $event['type'] === 'account.application.deauthorized' => $this->markAccountDeauthorized($event['data']),

            in_array($event['type'], ['account.external_account.created', 'account.external_account.updated'], true) => $this->upsertExternalAccount($event['data']),

            $event['type'] === 'account.external_account.deleted' => $this->deleteExternalAccount($event['data']),

            $event['type'] === 'capability.updated' => $this->syncCapability($event['data']),

            $this->isV2AccountEvent($event['type']) => $this->syncAccountFromV2Notification($event['notification']),

            default => false,
        };
    }

    /**
     * v2.core.account.* / v2.core.account[*].* — excludes v2.core.account_person.*
     */
    private function isV2AccountEvent(string $type): bool
    {
        return (bool) preg_match('/^v2\.core\.account(\.|\[)/', $type);
    }

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     version: 'v1'|'v2',
     *     data: array<string, mixed>,
     *     notification: EventNotification|null
     * }
     */
    private function parseEvent(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('stripe.webhook_secret', '');

        if ($secret === '') {
            throw new ServiceException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                __('messages.stripe_webhook_not_configured'),
            );
        }

        if ($signatureHeader === '') {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_signature'),
            );
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_payload'),
                $exception->getMessage(),
            );
        }

        if (($decoded['object'] ?? null) === 'v2.core.event') {
            try {
                $notification = StripeClient::get()->parseEventNotification(
                    $payload,
                    $signatureHeader,
                    $secret,
                );
            } catch (\UnexpectedValueException $exception) {
                throw new ServiceException(
                    Response::HTTP_BAD_REQUEST,
                    __('messages.stripe_webhook_invalid_payload'),
                    $exception->getMessage(),
                );
            } catch (SignatureVerificationException $exception) {
                throw new ServiceException(
                    Response::HTTP_BAD_REQUEST,
                    __('messages.stripe_webhook_invalid_signature'),
                    $exception->getMessage(),
                );
            }

            return [
                'id' => (string) $notification->id,
                'type' => (string) $notification->type,
                'version' => 'v2',
                'data' => [],
                'notification' => $notification,
            ];
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (\UnexpectedValueException $exception) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_payload'),
                $exception->getMessage(),
            );
        } catch (SignatureVerificationException $exception) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_signature'),
                $exception->getMessage(),
            );
        }

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'version' => 'v1',
            'data' => $this->toArray($event->data->object),
            'notification' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $object  v1 Account
     */
    private function syncAccountFromV1Object(array $object): bool
    {
        $stripeAccount = $this->findLocalAccount((string) ($object['id'] ?? ''));

        if ($stripeAccount === null) {
            return false;
        }

        $this->persistAccountRow($stripeAccount, $this->mapV1AccountFields($object, $stripeAccount));
        $this->syncExternalAccountsForLocalAccount($stripeAccount, $object);

        return true;
    }

    private function syncAccountFromV2Notification(?EventNotification $notification): bool
    {
        if ($notification === null) {
            return false;
        }

        $accountId = (string) ($notification->related_object->id ?? '');

        if ($accountId === '' || ! str_starts_with($accountId, 'acct_')) {
            return false;
        }

        $stripeAccount = $this->findLocalAccount($accountId);

        if ($stripeAccount === null) {
            Log::channel('stripe_webhook')->warning('Local Stripe account not found', [
                'stripe_account_id' => $accountId,
                'type' => $notification->type,
            ]);

            return false;
        }

        if ($notification->type === 'v2.core.account.closed') {
            $this->persistAccountRow($stripeAccount, [
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'onboarding_status' => StripeOnboardingStatusEnum::RESTRICTED,
                'requirements_disabled_reason' => 'account_closed',
                'last_synced_at' => Carbon::now(),
                'metadata' => array_merge($stripeAccount->metadata ?? [], [
                    'api' => 'v2',
                    'closed' => true,
                ]),
            ]);

            return true;
        }

        $v2Account = $this->retrieveV2Account($accountId);

        if ($v2Account === null) {
            $stripeAccount->update(['last_synced_at' => Carbon::now()]);

            return true;
        }

        Log::channel('stripe_webhook')->info('v2 account retrieved', [
            'stripe_account_id' => $accountId,
            'keys' => array_keys($v2Account),
            'has_external_accounts' => array_key_exists('external_accounts', $v2Account),
        ]);

        $this->persistAccountRow($stripeAccount, $this->mapV2AccountFields($v2Account, $stripeAccount));

        // v2 Account payloads do not include bank accounts — list via v1 external_accounts.
        $this->syncExternalAccountsForLocalAccount($stripeAccount);

        return true;
    }

    /**
     * Always fetch a full v2 account (thin events do not include field snapshots).
     *
     * @return array<string, mixed>|null
     */
    private function retrieveV2Account(string $accountId): ?array
    {
        try {
            $account = StripeClient::get()->request(
                'get',
                '/v2/core/accounts/'.urlencode($accountId),
                ['include' => self::V2_ACCOUNT_INCLUDES],
                [],
            );

            return $this->toArray($account);
        } catch (ApiErrorException $exception) {
            Log::channel('stripe_webhook')->error('Failed to retrieve v2 account', [
                'stripe_account_id' => $accountId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $object  v1 Account
     * @return array<string, mixed>
     */
    private function mapV1AccountFields(array $object, OrganisationStripeAccount $existing): array
    {
        $chargesEnabled = (bool) ($object['charges_enabled'] ?? false);
        $payoutsEnabled = (bool) ($object['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool) ($object['details_submitted'] ?? false);
        $requirements = is_array($object['requirements'] ?? null) ? $object['requirements'] : [];
        $currentlyDue = is_array($requirements['currently_due'] ?? null) ? $requirements['currently_due'] : [];
        $pastDue = is_array($requirements['past_due'] ?? null) ? $requirements['past_due'] : [];
        $disabledReason = filled($requirements['disabled_reason'] ?? null)
            ? (string) $requirements['disabled_reason']
            : null;

        $onboardingStatus = $this->resolveOnboardingStatus(
            closed: false,
            chargesEnabled: $chargesEnabled,
            detailsSubmitted: $detailsSubmitted,
            disabledReason: $disabledReason,
        );

        $controllerType = data_get($object, 'controller.stripe_dashboard.type')
            ?? data_get($object, 'type');

        return [
            'account_type' => filled($controllerType)
                ? (string) $controllerType
                : $existing->account_type,
            'country' => isset($object['country'])
                ? strtoupper((string) $object['country'])
                : $existing->country,
            'default_currency' => isset($object['default_currency'])
                ? strtolower((string) $object['default_currency'])
                : $existing->default_currency,
            'business_type' => isset($object['business_type'])
                ? (string) $object['business_type']
                : $existing->business_type,
            'email' => isset($object['email'])
                ? (string) $object['email']
                : $existing->email,
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
            'onboarding_status' => $onboardingStatus,
            'requirements_currently_due' => $currentlyDue,
            'requirements_past_due' => $pastDue,
            'requirements_disabled_reason' => $disabledReason,
            'onboarding_completed_at' => $this->resolveCompletedAt($existing, $onboardingStatus),
            'last_synced_at' => Carbon::now(),
            'metadata' => array_merge($existing->metadata ?? [], [
                'api' => 'v1',
                'last_event_object' => $object['object'] ?? 'account',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $account  v2 Account array
     * @return array<string, mixed>
     */
    private function mapV2AccountFields(array $account, OrganisationStripeAccount $existing): array
    {
        $cardPaymentsStatus = data_get($account, 'configuration.merchant.capabilities.card_payments.status');
        $payoutsStatus = data_get($account, 'configuration.merchant.capabilities.stripe_balance.payouts.status');

        $chargesEnabled = $cardPaymentsStatus === 'active';
        $payoutsEnabled = $payoutsStatus === 'active';

        [$currentlyDue, $pastDue, $disabledReason] = $this->mapV2Requirements(
            is_array($account['requirements'] ?? null) ? $account['requirements'] : [],
        );

        $detailsSubmitted = $currentlyDue === [] && $pastDue === [] && $disabledReason === null;
        $closed = (bool) ($account['closed'] ?? false);

        $onboardingStatus = $this->resolveOnboardingStatus(
            closed: $closed,
            chargesEnabled: $chargesEnabled,
            detailsSubmitted: $detailsSubmitted,
            disabledReason: $disabledReason,
        );

        $country = data_get($account, 'identity.country');
        $entityType = data_get($account, 'identity.entity_type');
        $currency = data_get($account, 'defaults.currency');
        $dashboard = $account['dashboard'] ?? null;

        return [
            'account_type' => filled($dashboard) ? (string) $dashboard : $existing->account_type,
            'country' => filled($country) ? strtoupper((string) $country) : $existing->country,
            'default_currency' => filled($currency) ? strtolower((string) $currency) : $existing->default_currency,
            'business_type' => filled($entityType) ? (string) $entityType : $existing->business_type,
            'email' => filled($account['contact_email'] ?? null)
                ? (string) $account['contact_email']
                : $existing->email,
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'details_submitted' => $detailsSubmitted,
            'onboarding_status' => $onboardingStatus,
            'requirements_currently_due' => $currentlyDue,
            'requirements_past_due' => $pastDue,
            'requirements_disabled_reason' => $disabledReason,
            'onboarding_completed_at' => $this->resolveCompletedAt($existing, $onboardingStatus),
            'last_synced_at' => Carbon::now(),
            'metadata' => array_merge($existing->metadata ?? [], [
                'api' => 'v2',
                'display_name' => $account['display_name'] ?? null,
                'applied_configurations' => $account['applied_configurations'] ?? null,
                'closed' => $closed,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $requirements
     * @return array{0: list<string>, 1: list<string>, 2: string|null}
     */
    private function mapV2Requirements(array $requirements): array
    {
        $currentlyDue = [];
        $pastDue = [];
        $disabledReason = null;

        $entries = is_array($requirements['entries'] ?? null) ? $requirements['entries'] : [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $description = (string) ($entry['description'] ?? $entry['reference']['type'] ?? 'requirement');
            $deadlineStatus = data_get($entry, 'minimum_deadline.status');

            if ($deadlineStatus === 'past_due') {
                $pastDue[] = $description;
            } else {
                $currentlyDue[] = $description;
            }

            $impact = data_get($entry, 'impact.restricts_capabilities');
            if (is_array($impact) && $impact !== [] && $disabledReason === null) {
                $disabledReason = 'requirements_pending';
            }
        }

        if ($pastDue !== [] && $disabledReason === null) {
            $disabledReason = 'requirements_past_due';
        }

        return [$currentlyDue, $pastDue, $disabledReason];
    }

    private function resolveOnboardingStatus(
        bool $closed,
        bool $chargesEnabled,
        bool $detailsSubmitted,
        ?string $disabledReason,
    ): StripeOnboardingStatusEnum {
        return match (true) {
            $closed, filled($disabledReason) => StripeOnboardingStatusEnum::RESTRICTED,
            $chargesEnabled && $detailsSubmitted => StripeOnboardingStatusEnum::COMPLETE,
            default => StripeOnboardingStatusEnum::PENDING,
        };
    }

    private function resolveCompletedAt(
        OrganisationStripeAccount $existing,
        StripeOnboardingStatusEnum $status,
    ): ?CarbonInterface {
        if ($status === StripeOnboardingStatusEnum::COMPLETE) {
            return $existing->onboarding_completed_at ?? Carbon::now();
        }

        return $existing->onboarding_completed_at;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persistAccountRow(OrganisationStripeAccount $stripeAccount, array $attributes): void
    {
        $stripeAccount->fill($attributes);
        $stripeAccount->save();

        Log::channel('stripe_webhook')->info('Account row updated', [
            'stripe_account_id' => $stripeAccount->stripe_account_id,
            'charges_enabled' => $stripeAccount->charges_enabled,
            'payouts_enabled' => $stripeAccount->payouts_enabled,
            'details_submitted' => $stripeAccount->details_submitted,
            'onboarding_status' => $stripeAccount->onboarding_status?->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function markAccountDeauthorized(array $object): bool
    {
        $stripeAccount = $this->findLocalAccount((string) ($object['id'] ?? ''));

        if ($stripeAccount === null) {
            return false;
        }

        $this->persistAccountRow($stripeAccount, [
            'charges_enabled' => false,
            'payouts_enabled' => false,
            'onboarding_status' => StripeOnboardingStatusEnum::RESTRICTED,
            'requirements_disabled_reason' => 'application_deauthorized',
            'last_synced_at' => Carbon::now(),
            'metadata' => array_merge($stripeAccount->metadata ?? [], [
                'deauthorized' => true,
            ]),
        ]);

        return true;
    }

    /**
     * Sync bank/card external accounts into organisation_stripe_external_accounts.
     *
     * Prefers embedded v1 `external_accounts.data` when present; otherwise lists via
     * GET /v1/accounts/{id}/external_accounts (required for Accounts v2 — that payload
     * never includes external_accounts).
     *
     * @param  array<string, mixed>|null  $v1AccountObject
     */
    private function syncExternalAccountsForLocalAccount(
        OrganisationStripeAccount $stripeAccount,
        ?array $v1AccountObject = null,
    ): void {
        $fromPayload = data_get($v1AccountObject, 'external_accounts.data');

        if (is_array($fromPayload) && $fromPayload !== []) {
            foreach ($fromPayload as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $item['account'] ??= $stripeAccount->stripe_account_id;
                $this->persistExternalAccountRow($stripeAccount, $item);
            }

            return;
        }

        try {
            $list = StripeClient::get()->accounts->allExternalAccounts(
                $stripeAccount->stripe_account_id,
                ['limit' => 100],
            );
        } catch (Throwable $exception) {
            Log::channel('stripe_webhook')->warning('Failed to list external accounts', [
                'stripe_account_id' => $stripeAccount->stripe_account_id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $count = 0;

        foreach ($list->data as $item) {
            $object = $this->toArray($item);
            $object['account'] ??= $stripeAccount->stripe_account_id;
            $this->persistExternalAccountRow($stripeAccount, $object);
            $count++;
        }

        Log::channel('stripe_webhook')->info('External accounts synced from Stripe API', [
            'stripe_account_id' => $stripeAccount->stripe_account_id,
            'count' => $count,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object  BankAccount / Card
     */
    private function upsertExternalAccount(array $object): bool
    {
        $stripeAccount = $this->findLocalAccount((string) ($object['account'] ?? ''));

        if ($stripeAccount === null) {
            return false;
        }

        if (! $this->persistExternalAccountRow($stripeAccount, $object)) {
            return false;
        }

        $stripeAccount->update(['last_synced_at' => Carbon::now()]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $object  BankAccount / Card
     */
    private function persistExternalAccountRow(OrganisationStripeAccount $stripeAccount, array $object): bool
    {
        $externalId = (string) ($object['id'] ?? '');

        if ($externalId === '') {
            return false;
        }

        OrganisationStripeExternalAccount::query()->updateOrCreate(
            ['stripe_external_account_id' => $externalId],
            [
                'organisation_stripe_account_id' => $stripeAccount->id,
                'object' => (string) ($object['object'] ?? 'bank_account'),
                'bank_name' => isset($object['bank_name']) ? (string) $object['bank_name'] : null,
                'last4' => isset($object['last4']) ? (string) $object['last4'] : null,
                'country' => isset($object['country']) ? strtoupper((string) $object['country']) : null,
                'currency' => isset($object['currency']) ? strtolower((string) $object['currency']) : null,
                'status' => isset($object['status']) ? (string) $object['status'] : null,
                'is_default_for_currency' => (bool) ($object['default_for_currency'] ?? false),
                'fingerprint' => isset($object['fingerprint']) ? (string) $object['fingerprint'] : null,
                'raw' => $object,
            ],
        );

        Log::channel('stripe_webhook')->info('External account upserted', [
            'stripe_account_id' => $stripeAccount->stripe_account_id,
            'stripe_external_account_id' => $externalId,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function deleteExternalAccount(array $object): bool
    {
        $externalId = (string) ($object['id'] ?? '');

        if ($externalId === '') {
            return false;
        }

        $deleted = OrganisationStripeExternalAccount::query()
            ->where('stripe_external_account_id', $externalId)
            ->delete();

        if ($deleted > 0 && filled($object['account'] ?? null)) {
            $this->findLocalAccount((string) $object['account'])
                ?->update(['last_synced_at' => Carbon::now()]);
        }

        return $deleted > 0;
    }

    /**
     * @param  array<string, mixed>  $object  Capability
     */
    private function syncCapability(array $object): bool
    {
        $stripeAccount = $this->findLocalAccount((string) ($object['account'] ?? ''));

        if ($stripeAccount === null) {
            return false;
        }

        $capabilityId = (string) ($object['id'] ?? '');
        $status = (string) ($object['status'] ?? '');
        $active = $status === 'active';

        $attributes = [
            'last_synced_at' => Carbon::now(),
            'metadata' => array_merge($stripeAccount->metadata ?? [], [
                'last_capability_id' => $capabilityId,
                'last_capability_status' => $status,
            ]),
        ];

        if (in_array($capabilityId, ['card_payments', 'card_issuing'], true)) {
            $attributes['charges_enabled'] = $active;
        }

        if (in_array($capabilityId, ['transfers', 'treasury'], true)) {
            $attributes['payouts_enabled'] = $active;
        }

        $chargesEnabled = (bool) ($attributes['charges_enabled'] ?? $stripeAccount->charges_enabled);
        $detailsSubmitted = $stripeAccount->details_submitted;
        $attributes['onboarding_status'] = $this->resolveOnboardingStatus(
            closed: false,
            chargesEnabled: $chargesEnabled,
            detailsSubmitted: $detailsSubmitted,
            disabledReason: $stripeAccount->requirements_disabled_reason,
        );
        $attributes['onboarding_completed_at'] = $this->resolveCompletedAt(
            $stripeAccount,
            $attributes['onboarding_status'],
        );

        $this->persistAccountRow($stripeAccount, $attributes);

        return true;
    }

    private function findLocalAccount(string $stripeAccountId): ?OrganisationStripeAccount
    {
        if ($stripeAccountId === '') {
            return null;
        }

        return OrganisationStripeAccount::query()
            ->where('stripe_account_id', $stripeAccountId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $object): array
    {
        if ($object instanceof StripeObject) {
            return $object->toArray();
        }

        if (is_array($object)) {
            return $object;
        }

        return [];
    }
}
