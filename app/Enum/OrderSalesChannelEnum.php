<?php

namespace App\Enum;

enum OrderSalesChannelEnum: string
{
    case ONLINE = 'online';
    case BOX_OFFICE = 'box_office';
    case COMPLIMENTARY = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Online',
            self::BOX_OFFICE => 'Box office',
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
