<?php

namespace App\Http\Resources;

use App\Models\OrganiserStaff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganiserStaff
 */
class StaffMemberListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $this->user_id,
            'name' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
            'email' => $user?->email,
            'profile_image' => $user?->profile_image,
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->map(static fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ])->values()->all(),
            ),
            'status' => $this->status?->value,
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
