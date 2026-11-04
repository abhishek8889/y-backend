<?php

namespace App\Enum;

enum SiteBannerFeatureTypeEnum: string
{
    case EVENTS = 'events';
    case NEWS = 'news';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::EVENTS => 'Events',
            self::NEWS => 'News',
            self::CUSTOM => 'Custom',
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
