<?php

namespace App\Http\Resources;

use App\Models\OrganisationCustomer;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List row for organisation customers (`organisation_customers` membership).
 *
 * @mixin OrganisationCustomer
 *
 * @property-read int|string|null $total_orders
 * @property-read int|string|float|null $total_spent
 * @property-read int|string|float|null $total_refunded
 */
class OrganisationCustomerListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;
        $lastOrder = $this->relationLoaded('lastOrder') ? $this->lastOrder : null;
        $currency = (string) ($lastOrder?->currency ?: 'GBP');
        $totalSpent = number_format((float) ($this->total_spent ?? 0), 2, '.', '');
        $totalRefunded = number_format((float) ($this->total_refunded ?? 0), 2, '.', '');

        return [
            'id' => $this->user_id,
            'membership_id' => $this->id,
            'first_name' => $user?->first_name,
            'last_name' => $user?->last_name,
            'name' => trim(($user?->first_name ?? '').' '.($user?->last_name ?? '')),
            'email' => $user?->email,
            'phone' => $user?->phone,
            'country_code' => $user?->country_code,
            'country' => $user?->country,
            'profile_image' => $user?->profile_image,
            'status' => $this->status?->value ?? $this->status,
            'total_orders' => (int) ($this->total_orders ?? 0),
            'total_spent' => $totalSpent,
            'formatted_total_spent' => MoneyFormatter::format($totalSpent, $currency),
            'total_refunded' => $totalRefunded,
            'formatted_total_refunded' => MoneyFormatter::format($totalRefunded, $currency),
            'currency' => $currency,
            'last_order' => $lastOrder === null ? null : [
                'id' => $lastOrder->id,
                'unique_id' => $lastOrder->unique_id,
                'total_amount' => (string) $lastOrder->total_amount,
                'currency' => $lastOrder->currency,
                'paid_at' => $lastOrder->paid_at?->toIso8601String(),
            ],
            'customer_since' => $this->created_at?->toIso8601String(),
        ];
    }
}
