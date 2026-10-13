<?php

namespace App\Http\Resources;

use App\Models\IssuedTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IssuedTicket
 */
class IssuedTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unique_id' => $this->unique_id,
            'code' => $this->code,
            'ticket_name' => $this->ticket_name,
            'offer_name' => $this->offer_name,
            'status' => $this->status?->value ?? $this->status,
            'status_updated_at' => $this->status_updated_at,
        ];
    }
}
