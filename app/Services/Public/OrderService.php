<?php

namespace App\Services\Public;

use App\Enum\OrderStatusEnum;
use App\Enum\StatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Order;
use App\Models\User;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class OrderService extends Service
{
    /**
     * Cursor-paginated purchase history for the authenticated customer.
     *
     * @param  array{
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function listForCustomer(User $user, array $filters = []): array
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                OrderStatusEnum::PAID,
                OrderStatusEnum::PARTIALLY_REFUNDED,
                OrderStatusEnum::REFUNDED,
            ])
            ->with([
                'items.event.venue.address',
                'items.event.images',
                'items.issuedTickets',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * One order for the authenticated customer, matched by unique id.
     *
     * @return array{order: Order}
     */
    public function statusForCustomer(User $user, string $orderUniqueId): array
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('unique_id', $orderUniqueId)
            ->with([
                'items.event.venue.address',
                'items.event.organisation:id,name',
            ])
            ->first();

        if ($order === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.order_not_found'),
            );
        }

        return [
            'order' => $order,
        ];
    }
}
