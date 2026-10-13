<?php

namespace App\Support;

use App\Enum\CurrencyEnum;

final class MoneyFormatter
{
    /**
     * Format an amount with the currency sign for a known code,
     * or fall back to "CODE 1,234.56" for unknown currencies.
     */
    public static function format(string|float|int $amount, string $currencyCode): string
    {
        $formattedAmount = number_format((float) $amount, 2, '.', ',');
        $currency = CurrencyEnum::tryFromCode($currencyCode);

        if ($currency === null) {
            $normalizedCode = strtoupper(trim($currencyCode));

            return ($normalizedCode === '' ? '' : $normalizedCode.' ').$formattedAmount;
        }

        return $currency->sign().$formattedAmount;
    }
}
