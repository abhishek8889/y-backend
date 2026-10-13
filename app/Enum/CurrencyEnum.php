<?php

namespace App\Enum;

enum CurrencyEnum: string
{
    case GBP = 'GBP';
    case USD = 'USD';
    case EUR = 'EUR';

    public function sign(): string
    {
        return match ($this) {
            self::GBP => '£',
            self::USD => '$',
            self::EUR => '€',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::GBP => 'British Pound',
            self::USD => 'US Dollar',
            self::EUR => 'Euro',
        };
    }

    /**
     * Resolve a currency code (case-insensitive) to this enum.
     */
    public static function tryFromCode(string $currencyCode): ?self
    {
        return self::tryFrom(strtoupper(trim($currencyCode)));
    }

    /**
     * Resolve a currency code to its sign, or null when unknown.
     */
    public static function signFor(string $currencyCode): ?string
    {
        return self::tryFromCode($currencyCode)?->sign();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
