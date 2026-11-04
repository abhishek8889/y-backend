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
     * Require any one of the given organisation permissions.
     *
     * Usage:
     * - organisation.permission:events.read
     * - organisation.permission:events.read,org_clubsites.read,org_clubsites.create,org_clubsites.update
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if ($permissions === []) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        $permissionEnums = [];

        foreach ($permissions as $permission) {
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

            $permissionEnums[] = $permissionEnum;
        }

        $user = $request->user();
        $organisationId = $user?->loginContext()['organisation_id'] ?? null;

        $organisation = $organisationId !== null
            ? Organisation::query()->find($organisationId)
            : null;

        if ($user === null || $organisation === null) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        $allowed = false;
        foreach ($permissionEnums as $permissionEnum) {
            if ($user->hasOrganisationPermission($organisation, $permissionEnum)) {
                $allowed = true;
                break;
            }
        }

        if (! $allowed) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        return $next($request);
    }
}
