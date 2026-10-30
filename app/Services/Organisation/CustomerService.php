<?php

namespace App\Services\Organisation;

use App\Enum\EventStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Enum\StatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Event;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
use App\Models\Refund;
use App\Models\User;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CustomerService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
     * Cursor-paginated orders for a customer linked to the authenticated organisation.
     *
     * @param  array{
     *     status?: string|null,
     *     search?: string|null,
     *     search_from?: string|null,
     *     search_to?: string|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{customer: User, paginator: CursorPaginator}
     */
    public function listOrders(User $actor, int $customerId, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $customer = $this->resolveOrganisationCustomer($organisation, $customerId);

        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $searchFrom = $this->parseUtcFilterDate($filters['search_from'] ?? null, false);
        $searchTo = $this->parseUtcFilterDate($filters['search_to'] ?? null, true);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if (filled($status) && OrderStatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_orders_invalid_status'),
            );
        }

        if ($searchFrom !== null && $searchTo !== null && $searchFrom->gt($searchTo)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_orders_invalid_date'),
            );
        }

        $paginator = Order::query()
            ->where('user_id', $customer->id)
            ->whereHas(
                'items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            )
            ->when(
                filled($status),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $searchFrom !== null,
                fn ($query) => $query->where('created_at', '>=', $searchFrom),
            )
            ->when(
                $searchTo !== null,
                fn ($query) => $query->where('created_at', '<=', $searchTo),
            )
            ->when(
                filled($search),
                function ($query) use ($search, $organisation): void {
                    $like = '%'.$search.'%';

                    $query->where(function ($inner) use ($like, $organisation): void {
                        $inner->where('sales_channel', 'like', $like)
                            ->orWhere('request_origin', 'like', $like)
                            ->orWhere('customer_name', 'like', $like)
                            ->orWhere('customer_email', 'like', $like)
                            ->orWhereHas('items', function ($itemQuery) use ($like, $organisation): void {
                                $itemQuery->where('organisation_id', $organisation->id)
                                    ->where(function ($itemInner) use ($like): void {
                                        $itemInner->where('ticket_name', 'like', $like)
                                            ->orWhereHas(
                                                'event',
                                                fn ($eventQuery) => $eventQuery->where('name', 'like', $like),
                                            );
                                    });
                            });
                    });
                },
            )
            ->with([
                'latestPayment',
                'items' => function ($query) use ($organisation): void {
                    $query->where('organisation_id', $organisation->id)
                        ->with('event:id,name');
                },
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'customer' => $customer,
            'paginator' => $paginator,
        ];
    }

    /**
     * Cursor-paginated purchased products (order items) for a customer linked to the organisation.
     *
     * @param  array{
     *     search?: string|null,
     *     status?: string|null,
     *     search_from?: string|null,
     *     search_to?: string|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{customer: User, paginator: CursorPaginator}
     */
    public function productList(User $actor, int $customerId, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $customer = $this->resolveOrganisationCustomer($organisation, $customerId);
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $searchFrom = $this->parseUtcFilterDate($filters['search_from'] ?? null, false);
        $searchTo = $this->parseUtcFilterDate($filters['search_to'] ?? null, true);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if (filled($status) && OrderStatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_orders_invalid_status'),
            );
        }

        if ($searchFrom !== null && $searchTo !== null && $searchFrom->gt($searchTo)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_orders_invalid_date'),
            );
        }

        $paginator = OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                function ($query) use ($customer, $status, $searchFrom, $searchTo): void {
                    $query->where('user_id', $customer->id)
                        ->when(
                            filled($status),
                            fn ($orderQuery) => $orderQuery->where('status', $status),
                        )
                        ->when(
                            $searchFrom !== null,
                            fn ($orderQuery) => $orderQuery->where('created_at', '>=', $searchFrom),
                        )
                        ->when(
                            $searchTo !== null,
                            fn ($orderQuery) => $orderQuery->where('created_at', '<=', $searchTo),
                        );
                },
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query->where(function ($inner) use ($like, $search): void {
                        $inner->where('ticket_name', 'like', $like)
                            ->orWhere('offer_name', 'like', $like)
                            ->orWhere('total_price', 'like', $like)
                            ->orWhereHas(
                                'eventTicket',
                                fn ($ticketQuery) => $ticketQuery->where('unique_id', 'like', $like),
                            )
                            ->orWhereHas(
                                'ticketOffer',
                                fn ($offerQuery) => $offerQuery->where('unique_id', 'like', $like),
                            )
                            ->orWhereHas(
                                'event',
                                fn ($eventQuery) => $eventQuery->where('name', 'like', $like),
                            )
                            ->orWhereHas('order', function ($orderQuery) use ($like): void {
                                $orderQuery->where(function ($orderInner) use ($like): void {
                                    $orderInner->where('unique_id', 'like', $like)
                                        ->orWhere('status', 'like', $like)
                                        ->orWhere('sales_channel', 'like', $like);
                                });
                            });

                        if (is_numeric($search)) {
                            $inner->orWhere(
                                'total_price',
                                number_format((float) $search, 2, '.', ''),
                            );
                        }
                    });
                },
            )
            ->with([
                'order:id,unique_id,status,sales_channel,paid_at,created_at',
                'event:id,name',
                'eventTicket:id,unique_id',
                'ticketOffer:id,unique_id',
                'issuedTickets:id,order_item_id,unique_id,code,status',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'customer' => $customer,
            'paginator' => $paginator,
        ];
    }

    /**
     * Cursor-paginated customers linked to the authenticated organisation.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $actor, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if (filled($status) && StatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_invalid_status'),
            );
        }

        $paginator = OrganisationCustomer::query()
            ->select('organisation_customers.*')
            ->selectSub(
                Order::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('orders.user_id', 'organisation_customers.user_id')
                    ->whereHas(
                        'items',
                        fn ($query) => $query->where('organisation_id', $organisation->id),
                    ),
                'total_orders',
            )
            ->selectSub(
                OrderItem::query()
                    ->selectRaw('coalesce(sum(order_items.total_price), 0)')
                    ->where('order_items.organisation_id', $organisation->id)
                    ->where('order_items.pricing_type', OrderItemPricingTypeEnum::PAID)
                    ->whereHas(
                        'order',
                        fn ($query) => $query
                            ->whereColumn('orders.user_id', 'organisation_customers.user_id')
                            ->whereIn('orders.status', [
                                OrderStatusEnum::PAID,
                                OrderStatusEnum::PARTIALLY_REFUNDED,
                                OrderStatusEnum::REFUNDED,
                            ]),
                    ),
                'total_spent',
            )
            ->selectSub(
                Refund::query()
                    ->selectRaw('coalesce(sum(refunds.amount), 0)')
                    ->where('refunds.status', RefundStatusEnum::SUCCEEDED)
                    ->whereHas(
                        'order',
                        fn ($query) => $query
                            ->whereColumn('orders.user_id', 'organisation_customers.user_id')
                            ->whereHas(
                                'items',
                                fn ($items) => $items->where('organisation_id', $organisation->id),
                            ),
                    ),
                'total_refunded',
            )
            ->where('organisation_id', $organisation->id)
            ->when(
                filled($status),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $nameParts = preg_split('/\s+/', $search, 2) ?: [];

                    $query->whereHas('user', function ($userQuery) use ($like, $nameParts): void {
                        $userQuery->where(function ($inner) use ($like, $nameParts): void {
                            $inner->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('phone', 'like', $like);

                            if (count($nameParts) === 2) {
                                $inner->orWhere(function ($nameQuery) use ($nameParts): void {
                                    $nameQuery->where('first_name', 'like', '%'.$nameParts[0].'%')
                                        ->where('last_name', 'like', '%'.$nameParts[1].'%');
                                });
                            }
                        });
                    });
                },
            )
            ->with([
                'user:id,first_name,last_name,email,phone,country_code,country,profile_image',
                'lastOrder:id,unique_id,total_amount,currency,paid_at',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Customer overview for the organisation dashboard.
     *
     * @return array{
     *     customer: User,
     *     membership: OrganisationCustomer,
     *     last_activity: string|null,
     *     summary: array{
     *         total_orders: int,
     *         total_tickets_purchased: int,
     *         total_spent: string,
     *         total_refunded: string,
     *         events_attended: int,
     *         currency: string|null
     *     },
     *     recent_activity: list<array<string, mixed>>,
     *     recent_orders: Collection<int, Order>,
     *     upcoming_events: Collection<int, Event>
     * }
     */
    public function overview(User $actor, int $customerId): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $customer = $this->resolveOrganisationCustomer($organisation, $customerId);

        /** @var OrganisationCustomer $membership */
        $membership = OrganisationCustomer::query()
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $customer->id)
            ->firstOrFail();

        $orgOrdersQuery = Order::query()
            ->where('user_id', $customer->id)
            ->whereHas(
                'items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            );

        $totalOrders = (clone $orgOrdersQuery)->count();

        $totalTicketsPurchased = (int) IssuedTicket::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $customer->id),
            )
            ->whereNotIn('status', [
                IssuedTicketStatusEnum::CANCELLED,
                IssuedTicketStatusEnum::REFUNDED,
            ])
            ->count();

        $totalSpent = (string) OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->where('pricing_type', OrderItemPricingTypeEnum::PAID)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->whereIn('status', [
                        OrderStatusEnum::PAID,
                        OrderStatusEnum::PARTIALLY_REFUNDED,
                        OrderStatusEnum::REFUNDED,
                    ]),
            )
            ->sum('total_price');

        $totalRefunded = (string) Refund::query()
            ->where('status', RefundStatusEnum::SUCCEEDED)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->whereHas(
                        'items',
                        fn ($items) => $items->where('organisation_id', $organisation->id),
                    ),
            )
            ->sum('amount');

        $currency = OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $customer->id),
            )
            ->orderByDesc('id')
            ->value('currency');

        $recentOrders = (clone $orgOrdersQuery)
            ->with([
                'latestPayment',
                'items' => function ($query) use ($organisation): void {
                    $query->where('organisation_id', $organisation->id)
                        ->with('event:id,name');
                },
            ])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentRefunds = Refund::query()
            ->where('status', RefundStatusEnum::SUCCEEDED)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->whereHas(
                        'items',
                        fn ($items) => $items->where('organisation_id', $organisation->id),
                    ),
            )
            ->with('order:id,unique_id,currency')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $recentActivity = $this->buildRecentActivity($recentOrders, $recentRefunds);

        $lastActivity = collect($recentActivity)
            ->pluck('occurred_at')
            ->filter()
            ->sortDesc()
            ->first();

        if ($lastActivity === null) {
            $lastActivity = $membership->updated_at?->toIso8601String()
                ?? $membership->created_at?->toIso8601String();
        }

        $customerEventIds = OrderItem::query()
            ->where('organisation_id', $organisation->id)
            ->whereHas(
                'order',
                fn ($query) => $query->where('user_id', $customer->id),
            )
            ->distinct()
            ->pluck('event_id');

        $upcomingEvents = Event::query()
            ->where('organisation_id', $organisation->id)
            ->whereIn('id', $customerEventIds)
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', now())
            ->with('venue:id,name,unique_id')
            ->orderBy('starts_at')
            ->limit(5)
            ->get();

        return [
            'customer' => $customer,
            'membership' => $membership,
            'last_activity' => $lastActivity,
            'summary' => [
                'total_orders' => $totalOrders,
                'total_tickets_purchased' => $totalTicketsPurchased,
                'total_spent' => number_format((float) $totalSpent, 2, '.', ''),
                'total_refunded' => number_format((float) $totalRefunded, 2, '.', ''),
                'events_attended' => 0,
                'currency' => $currency !== null ? (string) $currency : null,
            ],
            'recent_activity' => $recentActivity,
            'recent_orders' => $recentOrders,
            'upcoming_events' => $upcomingEvents,
        ];
    }

    /**
     * Customer profile for a user linked to the authenticated organisation.
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
        $customer = $this->resolveOrganisationCustomer($organisation, $customerId);

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
            ->where('pricing_type', OrderItemPricingTypeEnum::PAID)
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $customer->id)
                    ->whereIn('status', [
                        OrderStatusEnum::PAID,
                        OrderStatusEnum::PARTIALLY_REFUNDED,
                        OrderStatusEnum::REFUNDED,
                    ]),
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

    /**
     * @param  Collection<int, Order>  $recentOrders
     * @param  Collection<int, Refund>  $recentRefunds
     * @return list<array<string, mixed>>
     */
    private function buildRecentActivity(Collection $recentOrders, Collection $recentRefunds): array
    {
        $activities = collect();

        foreach ($recentOrders as $order) {
            $occurredAt = $order->paid_at ?? $order->created_at;

            $activities->push([
                'type' => 'order',
                'occurred_at' => $occurredAt?->toIso8601String(),
                'title' => 'Order placed',
                'description' => $order->unique_id,
                'order' => [
                    'id' => $order->id,
                    'unique_id' => $order->unique_id,
                    'status' => $order->status?->value ?? $order->status,
                    'total_amount' => (string) $order->total_amount,
                    'currency' => $order->currency,
                ],
            ]);
        }

        foreach ($recentRefunds as $refund) {
            $occurredAt = $refund->refunded_at ?? $refund->created_at;

            $activities->push([
                'type' => 'refund',
                'occurred_at' => $occurredAt?->toIso8601String(),
                'title' => 'Refund issued',
                'description' => $refund->unique_id,
                'refund' => [
                    'id' => $refund->id,
                    'unique_id' => $refund->unique_id,
                    'amount' => (string) $refund->amount,
                    'currency' => $refund->currency,
                    'status' => $refund->status?->value ?? $refund->status,
                    'order_id' => $refund->order_id,
                    'order_unique_id' => $refund->order?->unique_id,
                ],
            ]);
        }

        return $activities
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    private function resolveOrganisationCustomer(Organisation $organisation, int $customerId): User
    {
        $customer = User::query()->find($customerId);

        if ($customer === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_customer_not_found'),
            );
        }

        $belongsToOrganisation = OrganisationCustomer::query()
            ->where('organisation_id', $organisation->id)
            ->where('user_id', $customer->id)
            ->exists();

        if (! $belongsToOrganisation) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_customer_not_found'),
            );
        }

        return $customer;
    }

    private function parseUtcFilterDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $raw = trim((string) $value);

        try {
            $date = Carbon::parse($raw, 'UTC')->utc();
        } catch (Throwable) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_customer_orders_invalid_date'),
            );
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        }

        return $date;
    }
}
