<?php

namespace App\Enum;

/**
 * Who is sending the email: platform app or an organiser.
 */
enum MailSenderEnum: string
{
    case PLATFORM = 'platform';
    case ORGANISER = 'organiser';

    public function label(): string
    {
        return match ($this) {
            self::PLATFORM => 'Platform',
            self::ORGANISER => 'Organiser',
        };
    }
}
