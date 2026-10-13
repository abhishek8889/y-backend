<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class MyOrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency;

        return [
            'id' => $this->id,
            'ticket_name' => $this->ticket_name,
            'offer_name' => $this->offer_name,
            'quantity' => $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'total_price' => (string) $this->total_price,
            'currency' => $currency,
            'formatted_unit_price' => MoneyFormatter::format((string) $this->unit_price, $currency),
            'formatted_total_price' => MoneyFormatter::format((string) $this->total_price, $currency),
            'event' => $this->whenLoaded(
                'event',
                fn () => $this->event === null
                    ? null
                    : [
                        'id' => $this->event->id,
                        'unique_id' => $this->event->unique_id,
                        'name' => $this->event->name,
                        'starts_at' => $this->event->starts_at,
                        'ends_at' => $this->event->ends_at,
                        'timezone' => $this->event->timezone,
                        'venue' => $this->event->relationLoaded('venue')
                            ? ($this->event->venue === null
                                ? null
                                : [
                                    'id' => $this->event->venue->id,
                                    'unique_id' => $this->event->venue->unique_id,
                                    'name' => $this->event->venue->name,
                                    'address' => $this->event->venue->relationLoaded('address')
                                        ? ($this->event->venue->address === null
                                            ? null
                                            : VenueAddressResource::make($this->event->venue->address))
                                        : null,
                                ])
                            : null,
                    ],
            ),
            'issued_tickets' => $this->whenLoaded(
                'issuedTickets',
                fn () => IssuedTicketResource::collection($this->issuedTickets),
            ),
        ];
    }
}
