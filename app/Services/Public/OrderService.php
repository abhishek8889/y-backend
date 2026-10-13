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
                'items.issuedTickets',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }
}
