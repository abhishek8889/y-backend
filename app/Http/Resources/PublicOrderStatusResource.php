<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\VenueAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class PublicOrderStatusResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'unique_id' => $this->unique_id,
            'status' => $this->status?->value ?? $this->status,
            'sales_channel' => $this->sales_channel?->value ?? $this->sales_channel,
            'request_origin' => $this->request_origin,
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'platform_fee_amount' => (string) $this->platform_fee_amount,
            'total_amount' => (string) $this->total_amount,
            'currency' => (string) $this->currency,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'paid_at' => $this->paid_at,
            'tickets_pdf_path' => $this->tickets_pdf_path,
            'event' => $this->eventPayload($this->items->first()),
            'items' => $this->items->map(fn (OrderItem $item): array => [
                'ticket_name' => $item->ticket_name,
                'offer_name' => $item->offer_name,
                'quantity' => $item->quantity,
                'total_price' => (string) $item->total_price,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     starts_at: mixed,
     *     timezone: string|null,
     *     venue_name: string|null,
     *     location: string|null,
     *     organisation_name: string|null
     * }|null
     */
    private function eventPayload(?OrderItem $item): ?array
    {
        $event = $item?->event;

        if ($event === null) {
            return null;
        }

        $venue = $event->relationLoaded('venue') ? $event->venue : null;
        $address = $venue?->relationLoaded('address') ? $venue->address : null;

        return [
            'name' => $event->name,
            'starts_at' => $event->starts_at,
            'timezone' => $event->timezone,
            'venue_name' => $venue?->name,
            'location' => $this->eventLocation($address),
            'organisation_name' => $event->relationLoaded('organisation')
                ? $event->organisation?->name
                : null,
        ];
    }

    private function eventLocation(?VenueAddress $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $parts = array_values(array_filter([
            $address->city,
            $address->state,
            $address->country,
        ], fn (?string $part): bool => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
