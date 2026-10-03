<?php

namespace App\Http\Resources;

use App\Models\OrganiserStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganiserStaff
 */
class StaffMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'organisation_id' => $this->organisation_id,
            'description' => $this->description,
            'status' => $this->status?->value,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'country_code' => $this->user->country_code,
                'country' => $this->user->country,
                'profile_image' => $this->user->profile_image,
                'status' => $this->user->status?->value,
            ]),
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->map(static fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])->values()->all(),
            ),
            'assigned_role_names' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->pluck('name')->values()->all(),
            ),
            'joined_at' => $this->created_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
