<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\IssueOrderRefundRequest;
use App\Http\Resources\OrganisationRefundResource;
use App\Services\Organisation\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends Controller
{
    /**
     * Cursor-paginated refunds for the authenticated organisation.
     */
    public function listRefunds(Request $request, OrderService $orders): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'order_id' => $request->filled('order_id')
                    ? $request->integer('order_id')
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $orders->listRefunds($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_order_refunds_list'),
                OrganisationRefundResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Refund detail for a refund belonging to the authenticated organisation.
     */
    public function refundDetails(
        Request $request,
        OrderService $orders,
        int $refund_id,
    ): JsonResponse {
        try {
            $response = $orders->refundDetails($request->user(), $refund_id);

            return $this->success(
                __('messages.organisation_order_refund_details'),
                OrganisationRefundResource::make($response['refund']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Issue a refund for an order belonging to the authenticated organisation.
     */
    public function issueRefund(
        IssueOrderRefundRequest $request,
        OrderService $orders,
        int $order_id,
    ): JsonResponse {
        try {
            $response = $orders->issueRefund(
                $request->user(),
                $order_id,
                $request->validated(),
            );

            return $this->success(
                __('messages.organisation_order_refund_issued'),
                OrganisationRefundResource::make($response['refund']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
