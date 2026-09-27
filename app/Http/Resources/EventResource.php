<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'event_category_id' => $this->event_category_id,
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->category !== null
                    ? EventCategoryResource::make($this->category)
                    : null,
            ),
            'description' => $this->description,
            'venue_id' => $this->venue_id,
            'venue' => $this->whenLoaded(
                'venue',
                fn () => $this->venue !== null
                    ? VenueResource::make($this->venue)
                    : null,
            ),
            'capacity' => $this->capacity,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,
            'status' => $this->status?->value ?? $this->status,
            'published_at' => $this->published_at,
            'images' => $this->whenLoaded(
                'images',
                fn () => EventImageResource::collection($this->images),
            ),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
