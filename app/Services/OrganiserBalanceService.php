<?php

namespace App\Services;

use App\Enum\EventStatusEnum;
use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\OrganiserLedgerReferenceTypeEnum;
use App\Enum\PlatformSettingKeyEnum;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrganiserAccount;
use App\Models\OrganiserLedgerEntry;
use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrganiserBalanceService extends Service
{
    /**
     * On successful payment: earn + reserve organiser share (not available for payout yet).
     */
    public function creditReservedEarnings(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $idempotencyKey = 'earning_order_item_'.$item->id;

            if (OrganiserLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }

            $creditAmount = number_format((float) $item->organiser_share_amount, 2, '.', '');

            OrganiserLedgerEntry::query()->create([
                'organisation_id' => $item->organisation_id,
                'entry_type' => OrganiserLedgerEntryTypeEnum::EARNING,
                'reference_type' => OrganiserLedgerReferenceTypeEnum::ORDER_ITEM,
                'reference_id' => $item->id,
                'credit_amount' => $creditAmount,
                'debit_amount' => '0.00',
                'currency' => $item->currency,
                'idempotency_key' => $idempotencyKey,
                'description' => 'Ticket sale for order '.$order->unique_id.' (reserved until payout hold ends)',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'event_id' => $item->event_id,
                    'ticket_offer_id' => $item->ticket_offer_id,
                    'reserved' => true,
                ],
            ]);

            $this->applyReservedEarningToAccount($item, $creditAmount);
        }
    }

    /**
     * Move reserved earnings to available balance when event completed + hold days have passed.
     *
     * @return int Number of order items released
     */
    public function releaseEligibleReservedBalances(): int
    {
        $holdDays = $this->payoutHoldDays();
        $cutoff = Carbon::now()->subDays($holdDays);
        $released = 0;

        $earningEntries = OrganiserLedgerEntry::query()
            ->where('entry_type', OrganiserLedgerEntryTypeEnum::EARNING)
            ->where('reference_type', OrganiserLedgerReferenceTypeEnum::ORDER_ITEM)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('organiser_ledger_entries as released')
                    ->whereColumn('released.reference_id', 'organiser_ledger_entries.reference_id')
                    ->where('released.reference_type', OrganiserLedgerReferenceTypeEnum::ORDER_ITEM->value)
                    ->where('released.entry_type', OrganiserLedgerEntryTypeEnum::PAYOUT_RELEASED->value);
            })
            ->orderBy('id')
            ->get();

        foreach ($earningEntries as $earning) {
            $item = OrderItem::query()
                ->with('event')
                ->find($earning->reference_id);

            if ($item === null || $item->event === null) {
                continue;
            }

            if (! $this->isEligibleForRelease($item->event, $cutoff)) {
                continue;
            }

            DB::transaction(function () use ($earning, $item, &$released): void {
                $idempotencyKey = 'payout_released_order_item_'.$item->id;

                if (OrganiserLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                    return;
                }

                $amount = number_format((float) $earning->credit_amount, 2, '.', '');

                OrganiserLedgerEntry::query()->create([
                    'organisation_id' => $item->organisation_id,
                    'entry_type' => OrganiserLedgerEntryTypeEnum::PAYOUT_RELEASED,
                    'reference_type' => OrganiserLedgerReferenceTypeEnum::ORDER_ITEM,
                    'reference_id' => $item->id,
                    'credit_amount' => $amount,
                    'debit_amount' => '0.00',
                    'currency' => $item->currency,
                    'idempotency_key' => $idempotencyKey,
                    'description' => 'Reserved balance released for payout after event hold period',
                    'metadata' => [
                        'order_item_id' => $item->id,
                        'event_id' => $item->event_id,
                        'earning_ledger_id' => $earning->id,
                    ],
                ]);

                $this->moveReservedToAvailable($item, $amount);
                $released++;
            });
        }

        return $released;
    }

    private function isEligibleForRelease(Event $event, Carbon $cutoff): bool
    {
        if ($event->status !== EventStatusEnum::COMPLETED) {
            return false;
        }

        $completedAt = $event->completed_at ?? $event->ends_at ?? $event->updated_at;

        if ($completedAt === null) {
            return false;
        }

        return $completedAt->lte($cutoff);
    }

    private function applyReservedEarningToAccount(OrderItem $item, string $creditAmount): void
    {
        $account = OrganiserAccount::query()
            ->where('organisation_id', $item->organisation_id)
            ->where('currency', $item->currency)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            OrganiserAccount::query()->create([
                'organisation_id' => $item->organisation_id,
                'currency' => $item->currency,
                'total_earned' => $creditAmount,
                'total_paid' => '0.00',
                'total_reserved' => $creditAmount,
                'available_balance' => '0.00',
            ]);

            return;
        }

        $earned = round((float) $account->total_earned + (float) $creditAmount, 2);
        $reserved = round((float) $account->total_reserved + (float) $creditAmount, 2);

        $account->update([
            'total_earned' => number_format($earned, 2, '.', ''),
            'total_reserved' => number_format($reserved, 2, '.', ''),
        ]);
    }

    private function moveReservedToAvailable(OrderItem $item, string $amount): void
    {
        $account = OrganiserAccount::query()
            ->where('organisation_id', $item->organisation_id)
            ->where('currency', $item->currency)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            return;
        }

        $reserved = max(0, round((float) $account->total_reserved - (float) $amount, 2));
        $available = round((float) $account->available_balance + (float) $amount, 2);

        $account->update([
            'total_reserved' => number_format($reserved, 2, '.', ''),
            'available_balance' => number_format($available, 2, '.', ''),
        ]);
    }

    private function payoutHoldDays(): int
    {
        $value = PlatformSetting::getValue(PlatformSettingKeyEnum::BOOKING_PAYOUT_HOLD_DAYS);

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return max(0, (int) config('booking.payout_hold_days', 10));
    }
}
