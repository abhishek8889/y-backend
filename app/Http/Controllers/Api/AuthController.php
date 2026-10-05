<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OrganiserRegisterRequest;
use App\Http\Requests\Auth\OrganiserVerifyEmailRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\LoginResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    /**
     * Authenticate an organisation owner or staff member and return a JWT.
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
     * Authenticate a platform (super admin dashboard) user and return a JWT.
     */
    public function platformLogin(LoginRequest $request, AuthService $auth): JsonResponse
    {
        try {
            $credentials = $request->safe()->only(['email', 'password']);

            $response = $auth->platformLogin($credentials['email'], $credentials['password']);

            return $this->success(
                __('auth.authenticated'),
                LoginResource::make($response),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Return the authenticated user's profile, scope, roles, and permissions.
     */
    public function aboutMe(Request $request, AuthService $auth): JsonResponse
    {
        try {
            $response = $auth->aboutMe($request->user());

            return $this->success(
                __('auth.about_me'),
                [
                    'user' => AuthenticatedUserResource::make($response),
                ],
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
