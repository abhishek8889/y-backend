<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ResolveHostnameRequest;
use App\Http\Resources\PublicHostResolutionResource;
use App\Services\Public\HostResolutionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ResolveHostnameController extends Controller
{
    /**
     * Resolve the current customer-facing host (marketplace vs published microsite).
     */
    public function __invoke(
        ResolveHostnameRequest $request,
        HostResolutionService $hosts,
    ): JsonResponse {
        try {
            $resolved = $hosts->resolve(
                (string) $request->validated('hostname'),
            );

            return $this->success(
                __('messages.public_host_resolved'),
                PublicHostResolutionResource::make($resolved),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
