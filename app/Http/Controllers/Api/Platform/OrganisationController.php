<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ManageOrganisationApproveStatusRequest;
use App\Http\Resources\OrganisationResource;
use App\Services\Platform\OrganisationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class OrganisationController extends Controller
{
    /**
     * List organisations for platform users.
     */
    public function list(OrganisationService $organisation): JsonResponse
    {
        try {
            $response = $organisation->list();

            return $this->success(
                __('messages.platform_organisation_list'),
                OrganisationResource::collection($response['organisations']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Return one organisation by id for platform users.
     */
    public function getOrganisationDetailById(
        int $organisation_id,
        OrganisationService $organisation,
    ): JsonResponse {
        try {
            $response = $organisation->getOrganisationDetailById($organisation_id);

            return $this->success(
                __('messages.platform_organisation_details'),
                OrganisationResource::make($response['organisation']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Approve or reject an organisation.
     */
    public function manageApproveStatus(
        ManageOrganisationApproveStatusRequest $request,
        OrganisationService $organisation,
    ): JsonResponse {
        try {
            $data = $request->validated();

            $response = $organisation->manageApproveStatus(
                (int) $data['organisation_id'],
                (bool) $data['approve_status'],
                $data['approve_status_reason'] ?? null,
            );

            return $this->success(
                __('messages.platform_organisation_approve_status_updated'),
                OrganisationResource::make($response['organisation']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
