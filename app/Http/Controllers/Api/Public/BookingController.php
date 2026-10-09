<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StartBookingTicketRequest;
use App\Services\Public\BookingService;
use Illuminate\Http\JsonResponse;
use Throwable;

class BookingController extends Controller
{
    /**
     * Start booking tickets for the authenticated user.
     */
    public function startBookingTicket(
        StartBookingTicketRequest $request,
        BookingService $booking,
    ): JsonResponse {
        try {
            $response = $booking->startBookingTicket($request->user(), $request->validated());

            return $this->success(
                __('messages.public_booking_started'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
