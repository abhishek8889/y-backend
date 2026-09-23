<?php

namespace App\Http\Middleware;

use App\Enum\PermissionScopeEnum;
use App\Exceptions\ServiceException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $scope = $user?->loginContext()['scope'] ?? null;

        if ($scope !== PermissionScopeEnum::PLATFORM->value) {
            throw new ServiceException(
                Response::HTTP_FORBIDDEN,
                __('messages.platform_access_denied'),
            );
        }

        return $next($request);
    }
}
