<?php

namespace App\Enum;

enum OrganiserLedgerEntryTypeEnum: string
{
    // Ticket sale paid. Organiser share is earned but held (not withdrawable yet).
    // Example: customer pays £40, fee 5% → earning credit £38; account.total_reserved += 38.
    case EARNING = 'earning';

    // Organiser requested a payout; that amount is locked from available while transfer is pending.
    // Example: available £100, request £40 → payout_reserved £40; available becomes £60.
    case PAYOUT_RESERVED = 'payout_reserved';

    // Payout transfer completed to organiser (e.g. Stripe transfer succeeded).
    // Example: locked £40 sent → payout_paid £40; total_paid += 40.
    case PAYOUT_PAID = 'payout_paid';

    // Sale hold ended (event completed + hold days). Held earning becomes available for payout.
    // Example: reserved £38 released → payout_released £38; available_balance += 38.
    case PAYOUT_RELEASED = 'payout_released';

    // Customer/order refund reduces organiser entitlement.
    // Example: refund £20 of a sale → refund entry; reserved or available reduced.
    case REFUND = 'refund';

    // Manual platform correction (dispute, goodwill, accounting fix).
    // Example: admin adds £5 credit or removes £5 → adjustment entry.
    case ADJUSTMENT = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::EARNING => 'Earning',
            self::PAYOUT_RESERVED => 'Payout reserved',
            self::PAYOUT_PAID => 'Payout paid',
            self::PAYOUT_RELEASED => 'Payout released',
            self::REFUND => 'Refund',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
