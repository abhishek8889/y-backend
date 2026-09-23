<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->loginContext();

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country' => $this->country,
            'profile_image' => $this->profile_image,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'scope' => $context['scope'],
            'roles' => $context['roles'],
            'organisation_id' => $context['organisation_id'],
        ];
    }
}
