<?php

namespace App\Http\Resources;

use App\Enum\CurrencyEnum;
use App\Enum\RefundStatusEnum;
use App\Models\Order;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Order row for the event refunds list (orders that have one or more refunds).
 *
 * @mixin Order
 */
class OrganisationEventRefundOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency;

        $totalRefunded = $this->relationLoaded('refunds')
            ? number_format(
                (float) $this->refunds
                    ->where('status', RefundStatusEnum::SUCCEEDED)
                    ->sum(fn ($refund): float => (float) $refund->amount),
                2,
                '.',
                '',
            )
            : '0.00';

        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'status' => $this->status?->value ?? $this->status,
            'currency' => $currency,
            'currency_sign' => CurrencyEnum::signFor($currency),
            'total_amount' => (string) $this->total_amount,
            'formatted_total' => MoneyFormatter::format((string) $this->total_amount, $currency),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'total_refunded' => $totalRefunded,
            'formatted_total_refunded' => MoneyFormatter::format($totalRefunded, $currency),
            'refunds' => $this->whenLoaded(
                'refunds',
                fn () => OrganisationRefundResource::collection($this->refunds),
            ),
        ];
    }
}
