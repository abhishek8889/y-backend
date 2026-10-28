<?php

namespace App\Services;

use DateTimeZone;
use Locale;

class TimezoneService
{
    /**
     * PHP-supported timezone identifiers with resolved country metadata.
     *
     * @return list<array{timezone: string, country_code: string|null, country: string|null}>
     */
    public function list(): array
    {
        $items = [];

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $timezone) {
            $location = (new DateTimeZone($timezone))->getLocation();
            $countryCode = $location['country_code'] ?? null;

            if (! is_string($countryCode) || $countryCode === '' || $countryCode === '??') {
                $countryCode = null;
            } else {
                $countryCode = strtoupper($countryCode);
            }

            $items[] = [
                'timezone' => $timezone,
                'country_code' => $countryCode,
                'country' => $this->countryName($countryCode),
            ];
        }

        usort($items, function (array $a, array $b): int {
            $countryCompare = strcasecmp((string) ($a['country'] ?? ''), (string) ($b['country'] ?? ''));

            if ($countryCompare !== 0) {
                return $countryCompare;
            }

            return strcasecmp($a['timezone'], $b['timezone']);
        });

        return $items;
    }

    private function countryName(?string $countryCode): ?string
    {
        if ($countryCode === null) {
            return null;
        }

        if (! class_exists(Locale::class)) {
            return $countryCode;
        }

        $name = Locale::getDisplayRegion('-'.$countryCode, 'en');

        return filled($name) ? $name : $countryCode;
    }
}
