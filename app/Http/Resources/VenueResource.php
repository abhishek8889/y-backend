<?php

namespace App\Http\Resources;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Venue
 */
class VenueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'unique_id' => $this->unique_id,
            'name' => $this->name,
            'venue_type_id' => $this->venue_type_id,
            'venue_type' => $this->whenLoaded(
                'venueType',
                fn () => VenueTypeResource::make($this->venueType),
            ),
            'description' => $this->description,
            'maximum_capacity' => $this->maximum_capacity,
            'standing_capacity' => $this->standing_capacity,
            'seated_capacity' => $this->seated_capacity,
            'status' => $this->status,
            'is_private_hire_available' => $this->is_private_hire_available,
            'private_hire_description' => $this->private_hire_description,
            'address' => $this->whenLoaded(
                'address',
                fn () => $this->address !== null
                    ? VenueAddressResource::make($this->address)
                    : null,
            ),
            'contact' => $this->whenLoaded(
                'contacts',
                fn () => $this->contacts->isNotEmpty()
                    ? VenueContactResource::make($this->contacts->first())
                    : null,
            ),
            'accessibility' => $this->whenLoaded(
                'accessibility',
                fn () => $this->accessibility !== null
                    ? VenueAccessibilityResource::make($this->accessibility)
                    : null,
            ),
            'logistics' => $this->whenLoaded(
                'logistics',
                fn () => $this->logistics !== null
                    ? VenueLogisticsResource::make($this->logistics)
                    : null,
            ),
            'facilities' => $this->whenLoaded(
                'facilities',
                fn () => FacilityResource::collection($this->facilities),
            ),
            'suitable_for_options' => $this->whenLoaded(
                'suitableForOptions',
                fn () => VenueSuitableForOptionResource::collection($this->suitableForOptions),
            ),
            'images' => $this->whenLoaded(
                'images',
                fn () => VenueImageResource::collection($this->images),
            ),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
