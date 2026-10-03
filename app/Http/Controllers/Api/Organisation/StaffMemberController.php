<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\ManageStaffMemberStatusRequest;
use App\Http\Requests\Organisation\StoreOrganisationStaffMemberRequest;
use App\Http\Requests\Organisation\UpdateOrganisationStaffMemberRequest;
use App\Http\Resources\StaffMemberListResource;
use App\Http\Resources\StaffMemberResource;
use App\Services\Organisation\StaffMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class StaffMemberController extends Controller
{
    /**
     * Cursor-paginated staff members for the authenticated organisation.
     */
    public function list(
        Request $request,
        StaffMemberService $staffMember,
    ): JsonResponse {
        try {
            $filters = [
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $staffMember->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_staff_list'),
                StaffMemberListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Get staff member details by user id for the authenticated organisation.
     */
    public function details(
        Request $request,
        StaffMemberService $staffMember,
        int $member_id,
    ): JsonResponse {
        try {
            $response = $staffMember->details($request->user(), $member_id);

            return $this->success(
                __('messages.organisation_staff_details'),
                StaffMemberResource::make($response['staff_member']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update organiser_staff status by user id.
     */
    public function manageStatus(
        ManageStaffMemberStatusRequest $request,
        StaffMemberService $staffMember,
        int $member_id,
    ): JsonResponse {
        try {
            $response = $staffMember->manageStatus(
                $request->user(),
                $member_id,
                $request->validated('status'),
            );

            return $this->success(
                __('messages.organisation_staff_status_updated'),
                StaffMemberResource::make($response['staff_member']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create a staff member for the authenticated organiser's organisation.
     */
    public function create(
        StoreOrganisationStaffMemberRequest $request,
        StaffMemberService $staffMember,
    ): JsonResponse {
        try {
            $response = $staffMember->create($request->user(), $request->validated());

            return $this->success(
                __('messages.organisation_staff_created'),
                StaffMemberResource::make($response['staff_member']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update a staff member belonging to the authenticated organisation.
     */
    public function update(
        UpdateOrganisationStaffMemberRequest $request,
        StaffMemberService $staffMember,
        int $member_id,
    ): JsonResponse {
        try {
            $response = $staffMember->update(
                $request->user(),
                $member_id,
                $request->validated(),
            );

            return $this->success(
                __('messages.organisation_staff_updated'),
                StaffMemberResource::make($response['staff_member']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
