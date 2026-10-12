<?php

namespace App\Http\Resources;

use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wishlist
 */
class WishlistResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'event_id' => $this->event_id,
            'event' => $this->whenLoaded(
                'event',
                fn () => $this->event === null
                    ? null
                    : EventListResource::make($this->event),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
