<?php

namespace App\Http\Resources;

use App\Models\Refund;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class OrganisationRefundResource extends JsonResource
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
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'amount' => (string) $this->amount,
            'formatted_amount' => MoneyFormatter::format((string) $this->amount, $currency),
            'currency' => $currency,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'stripe_refund_id' => $this->stripe_refund_id,
            'requested_by' => $this->requested_by,
            'approved_by' => $this->approved_by,
            'failure_reason' => $this->failure_reason,
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'customer_notified_at' => $this->customer_notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'order' => $this->whenLoaded('order', fn () => $this->order === null ? null : [
                'id' => $this->order->id,
                'unique_id' => $this->order->unique_id,
                'status' => $this->order->status?->value,
                'customer_name' => $this->order->customer_name,
                'customer_email' => $this->order->customer_email,
                'customer_phone' => $this->order->customer_phone,
                'total_amount' => (string) $this->order->total_amount,
                'currency' => (string) $this->order->currency,
            ]),
            'payment' => $this->whenLoaded('payment', fn () => $this->payment === null ? null : [
                'id' => $this->payment->id,
                'provider' => $this->payment->provider?->value,
                'amount' => (string) $this->payment->amount,
                'currency' => (string) $this->payment->currency,
                'payment_intent_id' => $this->payment->payment_intent_id,
            ]),
            'requester' => $this->whenLoaded('requester', fn () => $this->requester === null ? null : [
                'id' => $this->requester->id,
                'first_name' => $this->requester->first_name,
                'last_name' => $this->requester->last_name,
                'email' => $this->requester->email,
            ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver === null ? null : [
                'id' => $this->approver->id,
                'first_name' => $this->approver->first_name,
                'last_name' => $this->approver->last_name,
                'email' => $this->approver->email,
            ]),
        ];
    }
}
