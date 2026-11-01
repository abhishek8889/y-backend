<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'vercel' => [
        'token' => env('VERCEL_TOKEN'),
        'project_id' => env('VERCEL_PROJECT_ID'),
        'team_id' => env('VERCEL_TEAM_ID'),
        'cname_target' => env('VERCEL_CNAME_TARGET'),
        'a_record_ip' => env('VERCEL_A_RECORD_IP', '216.150.1.1'),
        'base_url' => env('VERCEL_API_BASE_URL', 'https://api.vercel.com'),
        'webhook_secret' => env('VERCEL_WEBHOOK_SECRET'),
    ],

];
