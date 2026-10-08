<?php

namespace App\Http\Controllers\Api\Public;

use App\Enum\StatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StartBookingTicketRequest;
use App\Models\User;
use App\Services\JwtTokenService;
use App\Services\Public\BookingService;
use Illuminate\Http\JsonResponse;
use Throwable;

class BookingController extends Controller
{
    /**
     * Start booking tickets (authenticated customer or guest with name/email).
     */
    public function startBookingTicket(
        StartBookingTicketRequest $request,
        BookingService $booking,
        JwtTokenService $jwt,
    ): JsonResponse {
        try {
            $user = $this->resolveOptionalUser($request->bearerToken(), $jwt);

            $response = $booking->startBookingTicket($user, $request->validated());

            return $this->success(
                __('messages.public_booking_started'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    private function resolveOptionalUser(?string $token, JwtTokenService $jwt): ?User
    {
        if ($token === null || $token === '') {
            return null;
        }

        $user = User::query()->find($jwt->userId($token));

        if ($user === null || $user->status !== StatusEnum::ACTIVE) {
            return null;
        }

        return $user;
    }
}
