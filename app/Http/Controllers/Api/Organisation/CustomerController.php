<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationCustomerListResource;
use App\Http\Resources\OrganisationCustomerOrderListResource;
use App\Http\Resources\OrganisationCustomerOverviewResource;
use App\Http\Resources\OrganisationCustomerProductListResource;
use App\Http\Resources\OrganisationCustomerResource;
use App\Services\Organisation\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CustomerController extends Controller
{
    /**
     * Cursor-paginated customers linked to the authenticated organisation.
     */
    public function list(
        Request $request,
        CustomerService $customers,
    ): JsonResponse {
        try {
            $filters = [
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $customers->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_customer_list'),
                OrganisationCustomerListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

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

    /**
     * Customer overview for the organisation dashboard.
     */
    public function overview(
        Request $request,
        CustomerService $customers,
        int $customer_id,
    ): JsonResponse {
        try {
            $response = $customers->overview($request->user(), $customer_id);

            return $this->success(
                __('messages.organisation_customer_overview'),
                new OrganisationCustomerOverviewResource($response),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Cursor-paginated orders for a customer linked to the authenticated organisation.
     */
    public function listOrders(
        Request $request,
        CustomerService $customers,
        int $customer_id,
    ): JsonResponse {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'search_from' => $request->filled('search_from')
                    ? $request->string('search_from')->toString()
                    : null,
                'search_to' => $request->filled('search_to')
                    ? $request->string('search_to')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $customers->listOrders($request->user(), $customer_id, $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_customer_orders_list'),
                OrganisationCustomerOrderListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Cursor-paginated purchased products for a customer linked to the organisation.
     */
    public function productList(
        Request $request,
        CustomerService $customers,
        int $customer_id,
    ): JsonResponse {
        try {
            $filters = [
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'search_from' => $request->filled('search_from')
                    ? $request->string('search_from')->toString()
                    : null,
                'search_to' => $request->filled('search_to')
                    ? $request->string('search_to')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $customers->productList($request->user(), $customer_id, $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_customer_products_list'),
                OrganisationCustomerProductListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
