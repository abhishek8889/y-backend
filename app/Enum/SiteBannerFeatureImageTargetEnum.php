<?php

namespace App\Enum;

enum SiteBannerFeatureImageTargetEnum: string
{
    case FROM_FEATURE = 'from_feature';
    case CUSTOM_UPLOAD = 'custom_upload';

    public function label(): string
    {
        return match ($this) {
            self::FROM_FEATURE => 'From feature',
            self::CUSTOM_UPLOAD => 'Custom upload',
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
