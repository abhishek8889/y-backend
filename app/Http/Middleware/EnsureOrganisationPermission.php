<?php

namespace App\Http\Middleware;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Exceptions\ServiceException;
use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsureOrganisationPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        try {
            $permissionEnum = PermissionEnum::from($permission);
        } catch (ValueError) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        if ($permissionEnum->scope() !== PermissionScopeEnum::ORGANISATION) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        $user = $request->user();
        $organisationId = $user?->loginContext()['organisation_id'] ?? null;

        $organisation = $organisationId !== null
            ? Organisation::query()->find($organisationId)
            : null;

        if ($user === null || $organisation === null || ! $user->hasOrganisationPermission($organisation, $permissionEnum)) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        return $next($request);
    }
}
