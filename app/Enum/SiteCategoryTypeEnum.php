<?php

namespace App\Enum;

enum SiteCategoryTypeEnum: string
{
    case EVENTS = 'events';
    case NEWS = 'news';

    public function label(): string
    {
        return match ($this) {
            self::EVENTS => 'Events',
            self::NEWS => 'News',
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
