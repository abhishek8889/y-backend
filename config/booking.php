<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pending order hold window
    |--------------------------------------------------------------------------
    |
    | How long a pending order holds inventory before it can expire.
    |
    */

    'hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Payout hold after event completion
    |--------------------------------------------------------------------------
    |
    | Days to keep organiser earnings reserved after an event is completed
    | before those funds become available for payout.
    |
    */

    'payout_hold_days' => (int) env('BOOKING_PAYOUT_HOLD_DAYS', 10),

];
