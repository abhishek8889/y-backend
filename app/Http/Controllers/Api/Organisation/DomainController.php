<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\ConnectDomainRequest;
use App\Services\VercelService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DomainController extends Controller
{
    /**
     * Connect a domain on Vercel and return DNS records for the organiser.
     */
    public function connect(ConnectDomainRequest $request, VercelService $vercel): JsonResponse
    {
        try {
            $response = $vercel->connectDomain(
                $request->string('domain')->toString(),
            );

            return $this->success(
                __('messages.domain_connect_started'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Check domain verification and DNS configuration status on Vercel.
     */
    public function status(ConnectDomainRequest $request, VercelService $vercel): JsonResponse
    {
        try {
            $response = $vercel->getDomainStatus(
                $request->string('domain')->toString(),
            );

            return $this->success(
                __('messages.domain_status'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
