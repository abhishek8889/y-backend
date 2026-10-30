<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Purchased product row (order item) for a customer linked to the organisation.
 *
 * @mixin OrderItem
 */
class OrganisationCustomerProductListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency;
        $order = $this->relationLoaded('order') ? $this->order : null;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_unique_id' => $order?->unique_id,
            'status' => $order?->status?->value ?? $order?->status,
            'ticket_name' => $this->ticket_name,
            'offer_name' => $this->offer_name,
            'quantity' => $this->quantity,
            'total_price' => (string) $this->total_price,
            'formatted_total_price' => MoneyFormatter::format((string) $this->total_price, $currency),
            'currency' => $currency,
            'pricing_type' => $this->pricing_type?->value ?? $this->pricing_type,
            'event_name' => $this->whenLoaded(
                'event',
                fn () => $this->event?->name,
            ),
            'sales_channel' => $order?->sales_channel?->value ?? $order?->sales_channel,
            'paid_at' => $order?->paid_at,
            'created_at' => $order?->created_at ?? $this->created_at,
            'ticket_unique_id' => $this->whenLoaded(
                'eventTicket',
                fn () => $this->eventTicket?->unique_id,
            ),
            'offer_unique_id' => $this->whenLoaded(
                'ticketOffer',
                fn () => $this->ticketOffer?->unique_id,
            ),
            'issued_tickets' => $this->whenLoaded(
                'issuedTickets',
                fn () => $this->issuedTickets->map(static fn ($ticket): array => [
                    'unique_id' => $ticket->unique_id,
                    'code' => $ticket->code,
                    'status' => $ticket->status?->value ?? $ticket->status,
                ])->values()->all(),
            ),
        ];
    }
}
