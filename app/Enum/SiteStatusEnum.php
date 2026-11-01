<?php

namespace App\Enum;

enum SiteStatusEnum: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Live',
            self::DISABLED => 'Disabled',
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
