<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     user: User,
 *     access_token: string,
 *     token_type: string,
 *     expires_in: int,
 *     scope: string|null,
 *     roles: list<string>,
 *     organisation_id: int|null,
 *     permissions?: array<string, list<string>>
 * } $resource
 */
class LoginResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userPayload = [
            'user' => $this->resource['user'],
            'scope' => $this->resource['scope'],
            'roles' => $this->resource['roles'],
            'organisation_id' => $this->resource['organisation_id'],
        ];

        if (array_key_exists('permissions', $this->resource)) {
            $userPayload['permissions'] = $this->resource['permissions'];
        }

        return [
            'access_token' => $this->resource['access_token'],
            'token_type' => $this->resource['token_type'],
            'expires_in' => $this->resource['expires_in'],
            'user' => AuthenticatedUserResource::make($userPayload)->resolve(),
        ];
    }
}
