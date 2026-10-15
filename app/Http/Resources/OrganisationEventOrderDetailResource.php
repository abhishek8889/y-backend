<?php

namespace App\Http\Resources;

use App\Enum\CurrencyEnum;
use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrganisationEventOrderDetailResource extends JsonResource
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
            'currency' => $currency,
            'currency_sign' => CurrencyEnum::signFor($currency),
            'subtotal' => (string) $this->subtotal,
            'discount_amount' => (string) $this->discount_amount,
            'platform_fee_amount' => (string) $this->platform_fee_amount,
            'total_amount' => (string) $this->total_amount,
            'formatted_total' => MoneyFormatter::format((string) $this->total_amount, $currency),
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
                'user_id' => $this->user_id,
            ],
            'created_by' => $this->whenLoaded(
                'creator',
                fn () => $this->creator === null
                    ? null
                    : [
                        'id' => $this->creator->id,
                        'first_name' => $this->creator->first_name,
                        'last_name' => $this->creator->last_name,
                        'email' => $this->creator->email,
                    ],
            ),
            'payment' => $payment === null
                ? null
                : [
                    'provider' => $payment->provider?->value ?? $payment->provider,
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status?->value ?? $payment->status,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'formatted_amount' => MoneyFormatter::format((string) $payment->amount, (string) $payment->currency),
                    'reference_name' => $payment->reference_name,
                    'external_reference_id' => $payment->external_reference_id,
                    'payment_intent_id' => $payment->payment_intent_id,
                    'charge_id' => $payment->charge_id,
                    'notes' => $payment->notes,
                    'paid_at' => $payment->paid_at,
                ],
            'items' => $this->whenLoaded(
                'items',
                fn () => MyOrderItemResource::collection($this->items),
            ),
        ];
    }
}
