<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationCustomerResource;
use App\Services\Organisation\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CustomerController extends Controller
{
    /**
     * Customer details for a buyer linked to the authenticated organisation.
     */
    public function info(
        Request $request,
        CustomerService $customers,
        int $customer_id,
    ): JsonResponse {
        try {
            $response = $customers->info($request->user(), $customer_id);

            return $this->success(
                __('messages.organisation_customer_details'),
                new OrganisationCustomerResource(
                    $response['customer'],
                    $response['summary'],
                ),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
