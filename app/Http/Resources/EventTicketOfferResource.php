<?php

namespace App\Http\Resources;

use App\Models\EventTicketOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventTicketOffer
 */
class EventTicketOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_ticket_id' => $this->event_ticket_id,
            'unique_id' => $this->unique_id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity_cap' => $this->quantity_cap,
            'sale_starts_at' => $this->sale_starts_at,
            'sale_ends_at' => $this->sale_ends_at,
            'max_per_order' => $this->max_per_order,
            'access' => $this->access,
            'status' => $this->status?->value ?? $this->status,
            'sort_order' => $this->sort_order,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
