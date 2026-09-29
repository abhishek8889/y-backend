<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API
    |--------------------------------------------------------------------------
    */

    'secret' => env('STRIPE_SECRET', env('STRIPE_SECRET_KEY')),

    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Connect defaults
    |--------------------------------------------------------------------------
    */

    'connect' => [
        'account_type' => env('STRIPE_CONNECT_ACCOUNT_TYPE', 'custom'),
        'country' => env('STRIPE_CONNECT_DEFAULT_COUNTRY', 'GB'),
        'default_currency' => env('STRIPE_CONNECT_DEFAULT_CURRENCY', 'gbp'),
        'business_type' => env('STRIPE_CONNECT_BUSINESS_TYPE', 'company'),
        'return_url' => env('STRIPE_CONNECT_RETURN_URL'),
        'refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API version (optional pin)
    |--------------------------------------------------------------------------
    */

    'api_version' => env('STRIPE_API_VERSION'),

];
