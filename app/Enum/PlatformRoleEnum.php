<?php

namespace App\Enum;

enum PlatformRoleEnum: string
{
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
        };
    }
}
