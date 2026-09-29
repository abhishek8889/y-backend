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
     * Return the organisation's Stripe Connected Account details.
     */
    public function stripeAccountDetail(
        Request $request,
        StripeService $stripe,
    ): JsonResponse {
        try {
            $response = $stripe->resolveOrganisationStripeAccount($request->user());

            return $this->success(
                __('messages.stripe_connected_account_details'),
                $response['stripe_account']->load('externalAccounts'),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
