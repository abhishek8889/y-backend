<?php

use App\Enum\CurrencyEnum;
use App\Support\MoneyFormatter;

test('currency enum maps codes to signs', function () {
    expect(CurrencyEnum::GBP->sign())->toBe('£')
        ->and(CurrencyEnum::USD->sign())->toBe('$')
        ->and(CurrencyEnum::EUR->sign())->toBe('€')
        ->and(CurrencyEnum::signFor('gbp'))->toBe('£')
        ->and(CurrencyEnum::signFor('USD'))->toBe('$')
        ->and(CurrencyEnum::signFor('xyz'))->toBeNull();
});

test('money formatter prefixes known currency signs', function () {
    expect(MoneyFormatter::format('40.00', 'GBP'))->toBe('£40.00')
        ->and(MoneyFormatter::format(90, 'usd'))->toBe('$90.00')
        ->and(MoneyFormatter::format('12.5', 'EUR'))->toBe('€12.50')
        ->and(MoneyFormatter::format('1000', 'GBP'))->toBe('£1,000.00');
});

test('money formatter falls back to currency code for unknown currencies', function () {
    expect(MoneyFormatter::format('25.00', 'aud'))->toBe('AUD 25.00');
});
