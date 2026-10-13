<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\MyOrderResource;
use App\Services\Public\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrderController extends Controller
{
    /**
     * Cursor-paginated orders for the authenticated customer (with tickets + scan codes).
     */
    public function myOrdersList(Request $request, OrderService $orders): JsonResponse
    {
        try {
            $filters = [
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $orders->listForCustomer($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.my_orders_list'),
                MyOrderResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
