<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * @property-read array{
 *     orders_count: int,
 *     paid_orders_count: int,
 *     tickets_count: int,
 *     total_spent: string,
 *     currency: string|null,
 *     last_order_at: string|null
 * } $summary
 */
class OrganisationCustomerResource extends JsonResource
{
    /**
     * @param  array{
     *     orders_count: int,
     *     paid_orders_count: int,
     *     tickets_count: int,
     *     total_spent: string,
     *     currency: string|null,
     *     last_order_at: string|null
     * }  $summary
     */
    public function __construct($resource, private readonly array $summary)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->summary['currency'] ?? 'GBP';

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'country' => $this->country,
            'profile_image' => $this->profile_image,
            'status' => $this->status?->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'summary' => [
                'orders_count' => $this->summary['orders_count'],
                'paid_orders_count' => $this->summary['paid_orders_count'],
                'tickets_count' => $this->summary['tickets_count'],
                'total_spent' => $this->summary['total_spent'],
                'formatted_total_spent' => MoneyFormatter::format(
                    $this->summary['total_spent'],
                    $currency,
                ),
                'currency' => $this->summary['currency'],
                'last_order_at' => $this->summary['last_order_at'],
            ],
        ];
    }
}
