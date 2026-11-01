<?php

namespace App\Enum;

enum SiteTypeEnum: string
{
    case MARKETPLACE = 'marketplace';
    case CLUB = 'club';
    case WHITE_LABEL = 'white_label';

    public function label(): string
    {
        return match ($this) {
            self::MARKETPLACE => 'Marketplace',
            self::CLUB => 'Club Site',
            self::WHITE_LABEL => 'White Label',
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
