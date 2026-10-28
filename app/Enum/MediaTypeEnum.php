<?php

namespace App\Enum;

enum MediaTypeEnum: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';

    /**
     * Detect image vs video from a stored media path (by file extension).
     */
    public static function fromPath(string $path): self
    {
        $extension = strtolower((string) pathinfo(ltrim($path, '/'), PATHINFO_EXTENSION));

        if ($extension === '') {
            return self::IMAGE;
        }

        $videoExtensions = ['mp4', 'webm', 'mov'];

        if (function_exists('config') && app()->bound('config')) {
            $videoExtensions = array_map(
                'strtolower',
                config('media.video_mimes', $videoExtensions),
            );
        }

        if (in_array($extension, $videoExtensions, true)) {
            return self::VIDEO;
        }

        return self::IMAGE;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
