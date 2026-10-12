<?php

namespace App\Enum;

enum OrganiserLedgerReferenceTypeEnum: string
{
    case ORDER_ITEM = 'order_item';
    case PAYOUT_REQUEST = 'payout_request';
    case REFUND = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::ORDER_ITEM => 'Order item',
            self::PAYOUT_REQUEST => 'Payout request',
            self::REFUND => 'Refund',
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
