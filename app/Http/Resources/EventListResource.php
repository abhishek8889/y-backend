<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'name' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'timezone' => $this->timezone,
            'venue_name' => $this->whenLoaded(
                'venue',
                fn () => $this->venue?->name,
            ),
            'images' => $this->whenLoaded(
                'images',
                fn () => EventImageResource::collection(
                    $this->images->where('type', 'banner')->values(),
                ),
            ),
        ];
    }
}
