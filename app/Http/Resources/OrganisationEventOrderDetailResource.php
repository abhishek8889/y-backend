<?php

namespace App\Http\Resources;

use App\Enum\CurrencyEnum;
use App\Enum\RefundStatusEnum;
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
            'request_origin' => $this->request_origin,
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
                'country_code' => $this->whenLoaded(
                    'user',
                    fn () => $this->user?->country_code,
                ),
                'user_id' => $this->user_id,
                'orders_count' => (int) ($this->customer_orders_count ?? 0),
                'customer_since' => $this->customer_since,
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
                    'card_brand' => $payment->card_brand,
                    'card_last4' => $payment->card_last4,
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
            'refunds' => $this->whenLoaded(
                'refunds',
                fn () => OrganisationRefundResource::collection($this->refunds),
            ),
            'total_refunded' => $this->whenLoaded(
                'refunds',
                function (): string {
                    $total = $this->refunds
                        ->where('status', RefundStatusEnum::SUCCEEDED)
                        ->sum(fn ($refund): float => (float) $refund->amount);

                    return number_format((float) $total, 2, '.', '');
                },
            ),
            'formatted_total_refunded' => $this->whenLoaded(
                'refunds',
                function () use ($currency): string {
                    $total = $this->refunds
                        ->where('status', RefundStatusEnum::SUCCEEDED)
                        ->sum(fn ($refund): float => (float) $refund->amount);

                    return MoneyFormatter::format(
                        number_format((float) $total, 2, '.', ''),
                        $currency,
                    );
                },
            ),
            'items' => $this->whenLoaded(
                'items',
                fn () => MyOrderItemResource::collection($this->items),
            ),
        ];
    }
}
