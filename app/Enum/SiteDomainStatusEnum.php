<?php

namespace App\Enum;

enum SiteDomainStatusEnum: string
{
    case PENDING_DNS = 'pending_dns';
    case PENDING_VERIFICATION = 'pending_verification';
    case ACTIVE = 'active';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_DNS => 'Pending DNS',
            self::PENDING_VERIFICATION => 'Pending Verification',
            self::ACTIVE => 'Active',
            self::FAILED => 'Failed',
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
