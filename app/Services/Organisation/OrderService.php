<?php

namespace App\Services\Organisation;

use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\RefundSettlementService;
use App\Services\Service;
use App\Services\StripeService;
use App\Support\UniqueIdGenerator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrderService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly StripeService $stripe,
        private readonly RefundSettlementService $refundSettlement,
    ) {}

    /**
     * Issue a refund for an organisation order.
     *
     * @param  array{amount: numeric-string|float|int, reason?: string|null}  $data
     * @return array{refund: Refund}
     */
    public function issueRefund(User $actor, int $orderId, array $data): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $amount = number_format((float) $data['amount'], 2, '.', '');
        $reason = isset($data['reason']) && filled($data['reason'])
            ? trim((string) $data['reason'])
            : null;

        /** @var array{refund: Refund, payment: Payment, order: Order, uses_stripe: bool, remaining: float} $prepared */
        $prepared = DB::transaction(function () use ($actor, $organisation, $orderId, $amount, $reason): array {
            /** @var Order|null $order */
            $order = Order::query()
                ->whereKey($orderId)
                ->whereHas(
                    'items',
                    fn ($query) => $query->where('organisation_id', $organisation->id),
                )
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                $this->fail(
                    Response::HTTP_NOT_FOUND,
                    __('messages.order_not_found'),
                );
            }

            if (! in_array($order->status, [
                OrderStatusEnum::PAID,
                OrderStatusEnum::PARTIALLY_REFUNDED,
            ], true)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.organisation_order_not_refundable'),
                );
            }

            /** @var Payment|null $payment */
            $payment = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatusEnum::SUCCEEDED)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($payment === null) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.organisation_order_payment_missing'),
                );
            }

            $committedRefundTotal = (float) Refund::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [
                    RefundStatusEnum::PENDING,
                    RefundStatusEnum::PROCESSING,
                    RefundStatusEnum::SUCCEEDED,
                ])
                ->sum('amount');

            $remaining = round((float) $payment->amount - $committedRefundTotal, 2);

            if ($remaining <= 0 || (float) $amount > $remaining + 0.001) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.organisation_order_refund_exceeds_remaining'),
                );
            }

            $usesStripe = $payment->provider === PaymentProviderEnum::STRIPE
                && filled($payment->payment_intent_id);

            $refund = Refund::query()->create([
                'unique_id' => UniqueIdGenerator::generate('REF', 12),
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'order_item_id' => null,
                'requested_by' => $actor->id,
                'approved_by' => $actor->id,
                'stripe_refund_id' => null,
                'amount' => $amount,
                'currency' => $payment->currency,
                'reason' => $reason,
                'status' => RefundStatusEnum::PROCESSING,
                'failure_reason' => null,
                'refunded_at' => null,
            ]);

            return [
                'refund' => $refund,
                'payment' => $payment,
                'order' => $order,
                'uses_stripe' => $usesStripe,
                'remaining' => $remaining,
            ];
        });

        if (! $prepared['uses_stripe']) {
            return [
                'refund' => $this->refundSettlement->markSucceeded($prepared['refund']),
            ];
        }

        try {
            $stripeAmount = (float) $amount >= $prepared['remaining'] - 0.001
                ? null
                : $amount;

            $stripeRefund = $this->stripe->createRefund(
                (string) $prepared['payment']->payment_intent_id,
                $stripeAmount,
                [
                    'currency' => $prepared['payment']->currency,
                    'reason' => $reason,
                    'metadata' => [
                        'order_id' => (string) $prepared['order']->id,
                        'order_unique_id' => (string) $prepared['order']->unique_id,
                        'refund_unique_id' => (string) $prepared['refund']->unique_id,
                        'organisation_id' => (string) $organisation->id,
                    ],
                    'idempotency_key' => 'refund_'.$prepared['refund']->unique_id,
                ],
            );
        } catch (Throwable $exception) {
            $this->refundSettlement->markFailed(
                $prepared['refund'],
                $exception->getMessage(),
            );

            throw $exception;
        }

        $prepared['refund']->update([
            'stripe_refund_id' => $stripeRefund['id'],
        ]);

        $stripeStatus = strtolower($stripeRefund['status']);

        if ($stripeStatus === 'succeeded') {
            return [
                'refund' => $this->refundSettlement->markSucceeded(
                    $prepared['refund']->fresh() ?? $prepared['refund'],
                ),
            ];
        }

        if (in_array($stripeStatus, ['failed', 'canceled'], true)) {
            return [
                'refund' => $this->refundSettlement->markFailed(
                    $prepared['refund']->fresh() ?? $prepared['refund'],
                    __('messages.organisation_order_refund_stripe_failed'),
                ),
            ];
        }

        return [
            'refund' => $prepared['refund']->fresh() ?? $prepared['refund'],
        ];
    }

    /**
     * Cursor-paginated refunds for orders belonging to the authenticated organisation.
     *
     * @param  array{
     *     status?: string|null,
     *     search?: string|null,
     *     order_id?: int|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function listRefunds(User $actor, array $filters = []): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $orderId = isset($filters['order_id']) ? (int) $filters['order_id'] : null;

        if (filled($status) && RefundStatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.organisation_order_refund_invalid_status'),
            );
        }

        $paginator = Refund::query()
            ->whereHas(
                'order.items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            )
            ->when(
                filled($status),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $orderId !== null && $orderId > 0,
                fn ($query) => $query->where('order_id', $orderId),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';

                    $query->where(function ($inner) use ($like): void {
                        $inner->where('unique_id', 'like', $like)
                            ->orWhere('stripe_refund_id', 'like', $like)
                            ->orWhere('reason', 'like', $like)
                            ->orWhereHas('order', function ($orderQuery) use ($like): void {
                                $orderQuery->where('unique_id', 'like', $like)
                                    ->orWhere('customer_name', 'like', $like)
                                    ->orWhere('customer_email', 'like', $like)
                                    ->orWhere('customer_phone', 'like', $like);
                            });
                    });
                },
            )
            ->with([
                'order:id,unique_id,status,customer_name,customer_email,customer_phone,total_amount,currency',
                'payment:id,provider,amount,currency,payment_intent_id',
                'requester:id,first_name,last_name,email',
                'approver:id,first_name,last_name,email',
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Refund detail for a refund linked to the authenticated organisation.
     *
     * @return array{refund: Refund}
     */
    public function refundDetails(User $actor, int $refundId): array
    {
        $organisation = $this->organisation->getOrganisationDetail($actor)['organisation'];

        /** @var Refund|null $refund */
        $refund = Refund::query()
            ->whereKey($refundId)
            ->whereHas(
                'order.items',
                fn ($query) => $query->where('organisation_id', $organisation->id),
            )
            ->with([
                'order:id,unique_id,status,customer_name,customer_email,customer_phone,total_amount,currency',
                'payment:id,provider,amount,currency,payment_intent_id',
                'requester:id,first_name,last_name,email',
                'approver:id,first_name,last_name,email',
            ])
            ->first();

        if ($refund === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_order_refund_not_found'),
            );
        }

        return [
            'refund' => $refund,
        ];
    }
}
