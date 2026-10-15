<?php

namespace App\Enum;

enum PaymentProviderEnum: string
{
    case STRIPE = 'stripe';
    case CASH = 'cash';
    case CARD_TERMINAL = 'card_terminal';
    case COMPLIMENTARY = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::CASH => 'Cash',
            self::CARD_TERMINAL => 'Card terminal',
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
