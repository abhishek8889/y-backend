<?php

namespace App\Enum;

enum StripeOnboardingStatusEnum: string
{
    case NOT_STARTED = 'not_started';
    case PENDING = 'pending';
    case RESTRICTED = 'restricted';
    case COMPLETE = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::NOT_STARTED => 'Not started',
            self::PENDING => 'Pending',
            self::RESTRICTED => 'Restricted',
            self::COMPLETE => 'Complete',
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
