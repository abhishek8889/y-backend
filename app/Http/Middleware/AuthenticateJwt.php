<?php

namespace App\Http\Middleware;

use App\Enum\StatusEnum;
use App\Exceptions\ServiceException;
use App\Models\User;
use App\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(private JwtTokenService $jwt) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            throw ServiceException::unauthorized(__('auth.invalid_token'));
        }

        $user = User::query()->find($this->jwt->userId($token));

        if ($user === null || $user->status !== StatusEnum::ACTIVE) {
            throw ServiceException::unauthorized(__('auth.invalid_token'));
        }

        Auth::setUser($user);

        return $next($request);
    }
}
