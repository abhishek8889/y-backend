<?php

namespace App\Enum;

enum VerificationOtpTypeEnum: string
{
    case LOGIN_TYPE = 'login';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN_TYPE => 'Login',
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
