<?php

namespace App\Enum;

enum OrderItemPricingTypeEnum: string
{
    case PAID = 'paid';
    case COMPLIMENTARY = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            self::PAID => 'Paid',
            self::COMPLIMENTARY => 'Complimentary',
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
