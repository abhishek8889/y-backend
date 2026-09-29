<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Services\Organisation\OrganisationService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrgStripeController extends Controller
{
    /**
     * Create a Stripe Connected Account for the authenticated organiser's organisation.
     */
    public function createConnectedAccount(
        Request $request,
        OrganisationService $organisation,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $org = $organisation->getOrganisationDetail($request->user())['organisation'];
            $response = $stripe->createConnectedAccount($org, $request->all());

            return $this->success(
                __('messages.stripe_connected_account_created'),
                [
                    'stripe_account' => $response['stripe_account'],
                    'onboarding_link' => $response['onboarding_link'],
                ],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create an onboarding link for the organisation's Stripe Connected Account.
     */
    public function createOnboardingLink(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $stripeAccount = $stripe->resolveOrganisationStripeAccount($request->user())['stripe_account'];
            $response = $stripe->createAccountOnboardingLink($stripeAccount, $request->all());

            return $this->success(
                __('messages.stripe_onboarding_link_created'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create an account-update link for bank / KYC updates.
     */
    public function createUpdateLink(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $stripeAccount = $stripe->resolveOrganisationStripeAccount($request->user())['stripe_account'];
            $response = $stripe->createAccountUpdateLink($stripeAccount, $request->all());

            return $this->success(
                __('messages.stripe_update_link_created'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Sync the organisation's Stripe Connected Account from Stripe.
     */
    public function syncConnectedAccount(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $stripeAccount = $stripe->resolveOrganisationStripeAccount($request->user())['stripe_account'];
            $response = $stripe->syncConnectedAccount($stripeAccount);

            return $this->success(
                __('messages.stripe_connected_account_synced'),
                $response['stripe_account'],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Sync external bank accounts for the organisation's Stripe Connected Account.
     */
    public function syncExternalAccounts(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $stripeAccount = $stripe->resolveOrganisationStripeAccount($request->user())['stripe_account'];
            $response = $stripe->syncExternalAccounts($stripeAccount);

            return $this->success(
                __('messages.stripe_external_accounts_synced'),
                $response['external_accounts'],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Return the organisation's local Stripe Connected Account record.
     */
    public function details(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $response = $stripe->resolveOrganisationStripeAccount($request->user());

            return $this->success(
                __('messages.stripe_connected_account_details'),
                $response['stripe_account'],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
