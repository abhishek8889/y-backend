<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OrganiserRegisterRequest;
use App\Http\Requests\Auth\OrganiserVerifyEmailRequest;
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

            $response = $auth->login($credentials['email'], $credentials['password']);

            return $this->success(
                __('auth.authenticated'),
                LoginResource::make($response),
            );

        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Start organiser registration (temporary record + OTP).
     */
    public function registerOrganiser(OrganiserRegisterRequest $request, AuthService $auth): JsonResponse
    {
        try {
            $response = $auth->registerOrganiser($request->validated());

            return $this->success(
                __('auth.registered'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Verify organiser email OTP, then run onboarding.
     */
    public function verifyOrganiserEmail(OrganiserVerifyEmailRequest $request, AuthService $auth): JsonResponse
    {
        try {
            $response = $auth->verifyOrganiserEmail($request->validated());

            return $this->success(
                __('auth.email_verified'),
                LoginResource::make($response),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}

