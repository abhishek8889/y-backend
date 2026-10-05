<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media storage driver
    |--------------------------------------------------------------------------
    |
    | Supported: local, cloudinary, aws
    | Switch via MEDIA_DRIVER. Callers should not change.
    |
    */

    'driver' => env('MEDIA_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Local disk (Laravel filesystem disk name)
    |--------------------------------------------------------------------------
    */

    'local_disk' => env('MEDIA_LOCAL_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Temporary upload directory (relative to disk / cloud folder root)
    |--------------------------------------------------------------------------
    */

    'tmp_directory' => env('MEDIA_TMP_DIRECTORY', 'media/tmp'),

    /*
    |--------------------------------------------------------------------------
    | Orphan tmp file TTL (hours)
    |--------------------------------------------------------------------------
    */

    'tmp_ttl_hours' => (int) env('MEDIA_TMP_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    */

    'max_image_kilobytes' => (int) env('MEDIA_MAX_IMAGE_KB', 10240),

    'max_video_kilobytes' => (int) env('MEDIA_MAX_VIDEO_KB', 102400),

    'image_mimes' => ['jpeg', 'jpg', 'png', 'webp', 'gif', 'jfif'],

    'video_mimes' => ['mp4', 'webm', 'mov'],

    /*
    |--------------------------------------------------------------------------
    | Cloudinary
    |--------------------------------------------------------------------------
    |
    | All assets are stored under the configured folder (e.g. yourlist/...).
    | DB paths stay relative without that folder prefix.
    |
    */

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'folder' => env('CLOUDINARY_FOLDER', 'yourlist'),
        'secure' => (bool) env('CLOUDINARY_SECURE', true),
    ],

    'aws' => [
        'disk' => env('MEDIA_AWS_DISK', 's3'),
    ],

];
