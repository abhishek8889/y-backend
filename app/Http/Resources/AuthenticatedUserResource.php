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
 *     organisation_timezone?: string|null,
 *     organisation_approve_status?: bool,
 *     stripe_connect_account_created?: bool,
 *     connect_onboarding_status?: string
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
            'permissions' => $this->when(
                array_key_exists('permissions', $this->resource),
                $permissions === [] ? (object) [] : $permissions,
            ),
            'organisation_timezone' => $this->when(
                array_key_exists('organisation_timezone', $this->resource),
                $this->resource['organisation_timezone'] ?? null,
            ),
            'organisation_approve_status' => $this->when(
                array_key_exists('organisation_approve_status', $this->resource),
                $this->resource['organisation_approve_status'] ?? null,
            ),
            'stripe_connect_account_created' => $this->when(
                array_key_exists('stripe_connect_account_created', $this->resource),
                $this->resource['stripe_connect_account_created'] ?? false,
            ),
            'connect_onboarding_status' => $this->when(
                array_key_exists('connect_onboarding_status', $this->resource),
                $this->resource['connect_onboarding_status'] ?? null,
            ),
        ];
    }
}
