<?php

return [

    'secret' => env('JWT_SECRET'),

    'algo' => env('JWT_ALGO', 'HS256'),

    /*
    | Access token lifetime, in minutes.
    */
    'ttl' => (int) env('JWT_TTL', 60),

];
