<?php

namespace App\Enum;

enum PlatformSettingKeyEnum: string
{
    case BOOKING_PLATFORM_FEES_PERCENTAGE = 'booking_platform_fees_percentage';
    case BOOKING_PAYOUT_HOLD_DAYS = 'booking_payout_hold_days';

    public function label(): string
    {
        return match ($this) {
            self::BOOKING_PLATFORM_FEES_PERCENTAGE => 'Booking platform fees percentage',
            self::BOOKING_PAYOUT_HOLD_DAYS => 'Booking payout hold days',
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
