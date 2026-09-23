<?php

namespace App\Http\Middleware;

use App\Enum\PermissionEnum;
use App\Exceptions\ServiceException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsurePlatformPermission
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

        $user = $request->user();

        if ($user === null || ! $user->hasPlatformPermission($permissionEnum)) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.permission_denied'),
            );
        }

        return $next($request);
    }
}
