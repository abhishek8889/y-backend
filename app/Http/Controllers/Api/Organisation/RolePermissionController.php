<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreOrganisationRoleRequest;
use App\Http\Requests\Organisation\UpdateOrganisationRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleListResource;
use App\Http\Resources\RoleResource;
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
     * Cursor-paginated roles list for the authenticated organisation.
     */
    public function getRoleList(
        Request $request,
        RolePermissionService $rolePermission,
    ): JsonResponse {
        try {
            $filters = [
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $rolePermission->getRoleList($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.organisation_role_list'),
                RoleListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Get a role with its assigned permissions by slug.
     */
    public function getRoleWithPermissions(
        Request $request,
        RolePermissionService $rolePermission,
        string $role_slug,
    ): JsonResponse {
        try {
            $response = $rolePermission->getRoleWithPermissions(
                $request->user(),
                $role_slug,
            );

            return $this->success(
                __('messages.organisation_role_details'),
                RoleResource::make($response['role']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create an organisation role with the selected permissions.
     */
    public function createRoleWithPermission(
        StoreOrganisationRoleRequest $request,
        RolePermissionService $rolePermission,
    ): JsonResponse {
        try {
            $response = $rolePermission->createRoleWithPermission(
                $request->user(),
                $request->validated(),
            );

            return $this->success(
                __('messages.organisation_role_created'),
                RoleResource::make($response['role']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update an organisation role and its permissions by slug.
     */
    public function updateRoleWithPermission(
        UpdateOrganisationRoleRequest $request,
        RolePermissionService $rolePermission,
        string $role_slug,
    ): JsonResponse {
        try {
            $response = $rolePermission->updateRoleWithPermission(
                $request->user(),
                $role_slug,
                $request->validated(),
            );

            return $this->success(
                __('messages.organisation_role_updated'),
                RoleResource::make($response['role']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
