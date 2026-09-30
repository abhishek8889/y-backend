<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Services\Organisation\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RolePermissionController extends Controller
{
    /**
     * List all organisation permissions available for role assignment.
     */
    public function getAllOrganisationPermissions(
        Request $request,
        RolePermissionService $rolePermission,
    ): JsonResponse {
        try {
            $response = $rolePermission->getAllOrganisationPermissions($request->user());

            return $this->success(
                __('messages.organisation_permissions_list'),
                PermissionResource::collection($response['permissions']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create an organisation role with the selected permissions.
     */
    public function createRoleWithPermission(
        Request $request,
        RolePermissionService $rolePermission,
    ): JsonResponse {
        try {
            $response = $rolePermission->createRoleWithPermission($request->user(), $request->all());

            return $this->success(
                __('messages.organisation_role_created'),
                $response['role'],
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
