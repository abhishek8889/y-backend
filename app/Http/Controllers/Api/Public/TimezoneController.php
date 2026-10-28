<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\TimezoneService;
use Illuminate\Http\JsonResponse;
use Throwable;

class TimezoneController extends Controller
{
    /**
     * Unauthenticated list of PHP-supported timezones with country names.
     */
    public function list(TimezoneService $timezones): JsonResponse
    {
        try {
            return $this->success(
                __('messages.timezone_list'),
                $timezones->list(),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
