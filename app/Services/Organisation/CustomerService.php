<?php

namespace App\Services\Organisation;

use App\Enum\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Service;
use Symfony\Component\HttpFoundation\Response;

class CustomerService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
     * Customer profile for a user who has ordered with the authenticated organisation.
     *
     * @return array{
     *     customer: User,
     *     summary: array{
     *         orders_count: int,
     *         paid_orders_count: int,
     *         tickets_count: int,
     *         total_spent: string,
     *         currency: string|null,
     *         last_order_at: string|null
     *     }
     * }
     */
    public function info(User $actor, int $customerId): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        $customer = User::query()->find($customerId);

        if ($customer === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_customer_not_found'),
            );
        }

        $belongsToOrganisation = Order::query()
            ->where('user_id', $customer->id)
            ->whereHas(
                'items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            )
            ->exists();

        if (! $belongsToOrganisation) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_customer_not_found'),
            );
        }

        $orgOrdersQuery = Order::query()
            ->where('user_id', $customer->id)
            ->whereHas(
                'items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            );

        $ordersCount = (clone $orgOrdersQuery)->count();
        $paidOrdersCount = (clone $orgOrdersQuery)
            ->where('status', OrderStatusEnum::PAID)
            ->count();

        $ticketsCount = (int) OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->where('status', OrderStatusEnum::PAID),
            )
            ->sum('quantity');

        $totalSpent = (string) OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->where('status', OrderStatusEnum::PAID),
            )
            ->sum('total_price');

        $currency = OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $customer->id),
            )
            ->orderByDesc('id')
            ->value('currency');

        $lastOrder = (clone $orgOrdersQuery)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first(['id', 'paid_at', 'created_at']);

        $lastOrderAt = $lastOrder?->paid_at?->toIso8601String()
            ?? $lastOrder?->created_at?->toIso8601String();

        return [
            'customer' => $customer,
            'summary' => [
                'orders_count' => $ordersCount,
                'paid_orders_count' => $paidOrdersCount,
                'tickets_count' => $ticketsCount,
                'total_spent' => number_format((float) $totalSpent, 2, '.', ''),
                'currency' => $currency !== null ? (string) $currency : null,
                'last_order_at' => $lastOrderAt,
            ],
        ];
    }
}
