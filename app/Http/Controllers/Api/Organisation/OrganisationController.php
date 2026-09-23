<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganisationResource;
use App\Services\Organisation\OrganisationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OrganisationController extends Controller
{
    /**
     * Return organisation details for the authenticated user.
     */
    public function getOrganisationDetail(Request $request, OrganisationService $organisation): JsonResponse
    {
        try {
            $response = $organisation->getOrganisationDetail($request->user());

            return $this->success(
                __('messages.organisation_details'),
                OrganisationResource::make($response['organisation']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
