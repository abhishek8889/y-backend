<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AuthController extends Controller
{
    /**
     * Authenticate the user and return a JWT access token.
     */
    public function login(LoginRequest $request, AuthService $auth): JsonResponse
    {
        try {
            $credentials = $request->safe()->only(['email', 'password']);

            return $this->success(
                __('auth.authenticated'),
                LoginResource::make($auth->login($credentials['email'], $credentials['password'])),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
