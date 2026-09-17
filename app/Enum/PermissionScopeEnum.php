<?php

namespace App\Enum;

enum PermissionScopeEnum: string
{
    case PLATFORM = 'platform';
    case ORGANISATION = 'organisation';

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => 'Platform',
            self::ORGANISATION => 'Organisation',
        };
    }
}
