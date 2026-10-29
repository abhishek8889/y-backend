<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StartBookingTicketRequest;
use App\Services\Public\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            $data = $request->validated();
            $data['request_origin'] = $this->resolveRequestOrigin($request);

            $response = $booking->startBookingTicket($request->user(), $data);

            return $this->success(
                __('messages.public_booking_started'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Prefer explicit body value, then Origin, then Referer host.
     */
    private function resolveRequestOrigin(Request $request): ?string
    {
        foreach ([
            $request->input('request_origin'),
            $request->headers->get('Origin'),
            $request->headers->get('Referer'),
        ] as $candidate) {
            $origin = $this->normalizeOrigin(is_string($candidate) ? $candidate : null);

            if ($origin !== null) {
                return $origin;
            }
        }

        return null;
    }

    private function normalizeOrigin(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.(int) $parts['port'];
        }

        return $origin;
    }
}
