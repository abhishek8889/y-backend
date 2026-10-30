<?php

namespace App\Http\Resources;

use App\Enum\CurrencyEnum;
use App\Models\Order;
use App\Services\MediaService;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean order row for the organisation customer orders list.
 *
 * @mixin Order
 */
class OrganisationCustomerOrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency;
        $payment = $this->relationLoaded('latestPayment')
            ? $this->latestPayment
            : null;

        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'status' => $this->status?->value ?? $this->status,
            'sales_channel' => $this->sales_channel?->value ?? $this->sales_channel,
            'request_origin' => $this->request_origin,
            'currency_sign' => CurrencyEnum::signFor($currency),
            'formatted_total' => MoneyFormatter::format((string) $this->total_amount, $currency),
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'tickets_pdf_path' => $this->mediaUrl($this->tickets_pdf_path),
            'payment' => $payment === null
                ? null
                : [
                    'payment_method' => $payment->payment_method,
                    'card_brand' => $payment->card_brand,
                    'card_last4' => $payment->card_last4,
                ],
            'items' => $this->whenLoaded(
                'items',
                fn () => $this->items->map(static fn ($item): array => [
                    'id' => $item->id,
                    'ticket_name' => $item->ticket_name,
                    'offer_name' => $item->offer_name,
                    'quantity' => $item->quantity,
                    'event' => $item->relationLoaded('event') && $item->event !== null
                        ? [
                            'id' => $item->event->id,
                            'name' => $item->event->name,
                        ]
                        : null,
                ])->values()->all(),
            ),
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return app(MediaService::class)->url($path);
    }
}
