<?php

namespace App\Http\Resources;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Organisation
 */
class OrganisationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'organiser_name' => $this->organiser_name,
            'name' => $this->name,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'postal_code' => $this->postal_code,
            'website' => $this->website,
            'logo' => $this->logo,
            'banner' => $this->banner,
            'description' => $this->description,
            'complete_status' => $this->complete_status,
            'approve_status' => $this->approve_status,
            'approve_status_reason' => $this->approve_status_reason,
            'organiser' => $this->whenLoaded(
                'owner',
                fn () => UserResource::make($this->owner),
            ),
        ];
    }
}
