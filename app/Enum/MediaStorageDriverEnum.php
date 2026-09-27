<?php

namespace App\Enum;

enum MediaStorageDriverEnum: string
{
    case LOCAL = 'local';
    case CLOUDINARY = 'cloudinary';
    case AWS = 'aws';

    public static function fromConfig(): self
    {
        return self::tryFrom((string) config('media.driver', self::LOCAL->value))
            ?? self::LOCAL;
    }
}
