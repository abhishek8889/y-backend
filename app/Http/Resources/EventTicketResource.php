<?php

namespace App\Http\Resources;

use App\Models\EventTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventTicket
 */
class EventTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'unique_id' => $this->unique_id,
            'name' => $this->name,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'currency' => $this->currency,
            'quantity_cap' => $this->quantity_cap,
            'badge_label' => $this->badge_label,
            'entitlement' => $this->entitlement,
            'status' => $this->status?->value ?? $this->status,
            'sort_order' => $this->sort_order,
            'offers' => $this->whenLoaded(
                'offers',
                fn () => EventTicketOfferResource::collection($this->offers),
            ),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
