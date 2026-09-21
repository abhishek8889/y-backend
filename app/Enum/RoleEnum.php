<?php

namespace App\Enum;

enum RoleEnum: string
{
    /** Owner of the organisation. */
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
        };
    }
}
