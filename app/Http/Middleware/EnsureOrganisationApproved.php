<?php

namespace App\Http\Middleware;

use App\Exceptions\ServiceException;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationApproved
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organisationId = $user?->loginContext()['organisation_id'] ?? null;

        $organisation = $organisationId !== null
            ? Organisation::query()->find($organisationId)
            : null;

        if ($organisation === null) {
            throw new ServiceException(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_not_found'),
            );
        }

        if ($organisation->approve_status !== true) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.organisation_not_approved'),
                $organisation->approve_status_reason,
            );
        }

        return $next($request);
    }
}
