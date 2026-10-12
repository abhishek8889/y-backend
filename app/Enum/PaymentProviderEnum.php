<?php

namespace App\Enum;

enum PaymentProviderEnum: string
{
    case STRIPE = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
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
