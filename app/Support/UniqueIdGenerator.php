<?php

namespace App\Support;

use Illuminate\Support\Str;

final class UniqueIdGenerator
{
    /**
     * Generate a prefixed random id: prefix + random uppercase alphanumeric string.
     */
    public static function generate(string $prefix, int $length = 4): string
    {
        $length = max(1, $length);

        return $prefix.strtoupper(Str::random($length));
    }
}
