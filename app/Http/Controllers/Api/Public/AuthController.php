<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SendLoginOtpRequest;
use App\Http\Requests\Public\VerifyLoginOtpRequest;
use App\Http\Resources\LoginResource;
use App\Services\Public\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Throwable;

class AuthController extends Controller
{
    /**
     * Send a login OTP (creates the customer user when the email is new).
     */
    public function sendLoginOtp(
        SendLoginOtpRequest $request,
        CustomerAuthService $auth,
    ): JsonResponse {
        try {
            $response = $auth->sendLoginOtp($request->validated());

            return $this->success(
                __('auth.login_otp_sent'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Verify login OTP and return a JWT (without permissions).
     */
    public function verifyLoginOtp(
        VerifyLoginOtpRequest $request,
        CustomerAuthService $auth,
    ): JsonResponse {
        try {
            $response = $auth->verifyLoginOtp($request->validated());

            return $this->success(
                __('auth.authenticated'),
                LoginResource::make($response),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
