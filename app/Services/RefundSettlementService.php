<?php

namespace App\Services;

use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\OrganiserLedgerReferenceTypeEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\RefundStatusEnum;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrganiserAccount;
use App\Models\OrganiserLedgerEntry;
use App\Models\Refund;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies local side-effects when a refund reaches a terminal status.
 */
class RefundSettlementService extends Service
{
    public function __construct(
        private readonly OrderRefundMailService $refundMail,
    ) {}

    /**
     * Mark refund succeeded and settle order / tickets / organiser ledger (idempotent).
     */
    public function markSucceeded(Refund $refund): Refund
    {
        $settled = DB::transaction(function () use ($refund): array {
            /** @var Refund $refund */
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === RefundStatusEnum::SUCCEEDED) {
                return [
                    'refund' => $refund,
                    'newly_succeeded' => false,
                ];
            }

            $refund->update([
                'status' => RefundStatusEnum::SUCCEEDED,
                'failure_reason' => null,
                'refunded_at' => $refund->refunded_at ?? Carbon::now(),
            ]);

            $this->syncOrderStatus($refund->order_id);
            $this->debitOrganiserShare($refund->fresh() ?? $refund);

            return [
                'refund' => $refund->fresh() ?? $refund,
                'newly_succeeded' => true,
            ];
        });

        if ($settled['newly_succeeded']) {
            $this->refundMail->queueAfterSuccessfulRefund($settled['refund']);
        }

        return $settled['refund'];
    }

    /**
     * Mark refund failed without changing order / tickets / ledger.
     */
    public function markFailed(Refund $refund, ?string $failureReason = null): Refund
    {
        return DB::transaction(function () use ($refund, $failureReason): Refund {
            /** @var Refund $refund */
            $refund = Refund::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();

            if ($refund->status === RefundStatusEnum::SUCCEEDED) {
                return $refund;
            }

            $refund->update([
                'status' => RefundStatusEnum::FAILED,
                'failure_reason' => $failureReason,
            ]);

            return $refund->fresh() ?? $refund;
        });
    }

    private function syncOrderStatus(int $orderId): void
    {
        /** @var Order $order */
        $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
        $order->loadMissing(['payments', 'items']);

        $payment = $order->payments
            ->sortByDesc('id')
            ->first(fn ($payment): bool => $payment->status === PaymentStatusEnum::SUCCEEDED)
            ?? $order->payments->sortByDesc('id')->first();

        if ($payment === null) {
            return;
        }

        $refundedTotal = (float) Refund::query()
            ->where('order_id', $order->id)
            ->where('status', RefundStatusEnum::SUCCEEDED)
            ->sum('amount');

        $paymentAmount = (float) $payment->amount;
        $isFullyRefunded = $refundedTotal >= $paymentAmount - 0.001;

        $order->update([
            'status' => $isFullyRefunded
                ? OrderStatusEnum::REFUNDED
                : OrderStatusEnum::PARTIALLY_REFUNDED,
        ]);

        if ($isFullyRefunded) {
            $organisationIds = $order->items->pluck('organisation_id')->unique()->all();

            IssuedTicket::query()
                ->where('order_id', $order->id)
                ->when(
                    $organisationIds !== [],
                    fn ($query) => $query->whereIn('organisation_id', $organisationIds),
                )
                ->where('status', '!=', IssuedTicketStatusEnum::REFUNDED)
                ->update([
                    'status' => IssuedTicketStatusEnum::REFUNDED,
                    'status_updated_at' => Carbon::now(),
                ]);
        }
    }

    private function debitOrganiserShare(Refund $refund): void
    {
        $idempotencyKey = 'refund_'.$refund->id;

        if (OrganiserLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $order = Order::query()->with('items')->find($refund->order_id);

        if ($order === null) {
            return;
        }

        $orderTotal = (float) $order->total_amount;

        if ($orderTotal <= 0) {
            return;
        }

        $itemsByOrganisation = $order->items->groupBy('organisation_id');

        foreach ($itemsByOrganisation as $organisationId => $items) {
            $organiserShareTotal = (float) $items->sum(fn ($item): float => (float) $item->organiser_share_amount);

            if ($organiserShareTotal <= 0) {
                continue;
            }

            $debitAmount = round(
                ((float) $refund->amount) * ($organiserShareTotal / $orderTotal),
                2,
            );

            if ($debitAmount <= 0) {
                continue;
            }

            $currency = (string) ($items->first()?->currency ?? $refund->currency);
            $orgIdempotencyKey = $itemsByOrganisation->count() > 1
                ? $idempotencyKey.'_org_'.$organisationId
                : $idempotencyKey;

            if (OrganiserLedgerEntry::query()->where('idempotency_key', $orgIdempotencyKey)->exists()) {
                continue;
            }

            $debitFormatted = number_format($debitAmount, 2, '.', '');

            OrganiserLedgerEntry::query()->create([
                'organisation_id' => (int) $organisationId,
                'entry_type' => OrganiserLedgerEntryTypeEnum::REFUND,
                'reference_type' => OrganiserLedgerReferenceTypeEnum::REFUND,
                'reference_id' => $refund->id,
                'credit_amount' => '0.00',
                'debit_amount' => $debitFormatted,
                'currency' => $currency,
                'idempotency_key' => $orgIdempotencyKey,
                'description' => 'Refund for order '.$order->unique_id,
                'metadata' => [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id,
                    'refund_amount' => (string) $refund->amount,
                ],
            ]);

            $this->applyRefundDebitToAccount((int) $organisationId, $currency, $debitFormatted);
        }
    }

    private function applyRefundDebitToAccount(int $organisationId, string $currency, string $debitAmount): void
    {
        $account = OrganiserAccount::query()
            ->where('organisation_id', $organisationId)
            ->where('currency', $currency)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            return;
        }

        $remaining = (float) $debitAmount;
        $reserved = (float) $account->total_reserved;
        $available = (float) $account->available_balance;
        $earned = (float) $account->total_earned;

        $fromReserved = min($reserved, $remaining);
        $reserved = round($reserved - $fromReserved, 2);
        $remaining = round($remaining - $fromReserved, 2);

        $fromAvailable = min($available, $remaining);
        $available = round($available - $fromAvailable, 2);
        $remaining = round($remaining - $fromAvailable, 2);

        $earned = round(max(0, $earned - ((float) $debitAmount - $remaining)), 2);

        $account->update([
            'total_earned' => number_format($earned, 2, '.', ''),
            'total_reserved' => number_format(max(0, $reserved), 2, '.', ''),
            'available_balance' => number_format(max(0, $available), 2, '.', ''),
        ]);
    }
}
