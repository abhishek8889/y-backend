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
class MyOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency;

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
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'items' => $this->whenLoaded(
                'items',
                fn () => MyOrderItemResource::collection($this->items),
            ),
        ];
    }
}
