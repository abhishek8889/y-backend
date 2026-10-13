<?php

namespace App\Enum;

enum IssuedTicketStatusEnum: string
{
    case VALID = 'valid';
    case CHECKED_IN = 'checked_in';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::VALID => 'Valid',
            self::CHECKED_IN => 'Checked in',
            self::CANCELLED => 'Cancelled',
            self::REFUNDED => 'Refunded',
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
