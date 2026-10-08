<?php

namespace App\Services\Public;

use App\Models\User;
use App\Services\Service;

class BookingService extends Service
{
    /**
     * Start a public ticket booking checkout.
     *
     * @param  array{
     *     offers: list<array{offer_id: int, qty: int}>,
     *     name?: string|null,
     *     email?: string|null
     * }  $data
     * @return array<string, mixed>
     */
    public function startBookingTicket(?User $user, array $data): array
    {
        // TODO: resolve/create guest user, validate offers, create pending order + PaymentIntent.
        
        return [];
    }
}
