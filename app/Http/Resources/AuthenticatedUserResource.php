<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     user: User,
 *     scope: string|null,
 *     roles: list<string>,
 *     organisation_id: int|null,
 *     permissions: array<string, list<string>>,
 *     organisation_approve_status?: bool
 * } $resource
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->resource['permissions'] ?? [];

        return [
            ...UserResource::make($this->resource['user'])->resolve(),
            'scope' => $this->resource['scope'],
            'roles' => $this->resource['roles'],
            'organisation_id' => $this->resource['organisation_id'],
            'permissions' => $permissions === [] ? (object) [] : $permissions,
            'organisation_approve_status' => $this->when(
                array_key_exists('organisation_approve_status', $this->resource),
                $this->resource['organisation_approve_status'] ?? null,
            ),
        ];
    }
}
