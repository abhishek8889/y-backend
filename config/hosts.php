<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace / common public hosts
    |--------------------------------------------------------------------------
    |
    | Hostnames that resolve as host_type=public (no organisation microsite).
    | Comma-separated in MARKETPLACE_HOSTS env, e.g.
    | yourlist.co.uk,www.yourlist.co.uk,dev-public.yourlist.co.uk
    |
    */

    'marketplace' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('MARKETPLACE_HOSTS', 'yourlist.co.uk,www.yourlist.co.uk')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Treat www and apex marketplace hosts as equivalent
    |--------------------------------------------------------------------------
    */

    'equate_www' => (bool) env('MARKETPLACE_HOSTS_EQUATE_WWW', true),

];
