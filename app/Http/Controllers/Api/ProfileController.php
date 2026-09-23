<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Return the authenticated user's profile details.
     */
    public function details(Request $request, ProfileService $profile): JsonResponse
    {
        try {
            $response = $profile->details($request->user());

            return $this->success(
                __('messages.profile_details'),
                ProfileResource::make($response['user']),
            );

        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
