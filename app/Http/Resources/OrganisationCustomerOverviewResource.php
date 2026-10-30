<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\OrganisationCustomer;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property-read array{
 *     customer: User,
 *     membership: OrganisationCustomer,
 *     last_activity: string|null,
 *     summary: array{
 *         total_orders: int,
 *         total_tickets_purchased: int,
 *         total_spent: string,
 *         total_refunded: string,
 *         events_attended: int,
 *         currency: string|null
 *     },
 *     recent_activity: list<array<string, mixed>>,
 *     recent_orders: Collection<int, mixed>,
 *     upcoming_events: Collection<int, Event>
 * } $resource
 */
class OrganisationCustomerOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $customer */
        $customer = $this->resource['customer'];
        /** @var OrganisationCustomer $membership */
        $membership = $this->resource['membership'];
        $summary = $this->resource['summary'];
        $currency = $summary['currency'] ?? 'GBP';

        return [
            'customer' => [
                'id' => $customer->id,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'email' => $customer->email,
                'profile_image' => $customer->profile_image,
                'phone' => $customer->phone,
                'country_code' => $customer->country_code,
                'country' => $customer->country,
                'status' => $customer->status?->value ?? $customer->status,
                'organisation_customer_status' => $membership->status?->value ?? $membership->status,
                'customer_since' => $membership->created_at?->toIso8601String(),
                'last_activity' => $this->resource['last_activity'],
            ],
            'summary' => [
                'total_orders' => $summary['total_orders'],
                'total_tickets_purchased' => $summary['total_tickets_purchased'],
                'total_spent' => $summary['total_spent'],
                'formatted_total_spent' => MoneyFormatter::format($summary['total_spent'], $currency),
                'total_refunded' => $summary['total_refunded'],
                'formatted_total_refunded' => MoneyFormatter::format($summary['total_refunded'], $currency),
                'events_attended' => $summary['events_attended'],
                'currency' => $summary['currency'],
            ],
            'recent_activity' => $this->resource['recent_activity'],
            'recent_orders' => OrganisationCustomerOrderListResource::collection(
                $this->resource['recent_orders'],
            ),
            'upcoming_events' => $this->resource['upcoming_events']
                ->map(static fn (Event $event): array => [
                    'id' => $event->id,
                    'unique_id' => $event->unique_id,
                    'name' => $event->name,
                    'starts_at' => $event->starts_at,
                    'ends_at' => $event->ends_at,
                    'timezone' => $event->timezone,
                    'status' => $event->status?->value ?? $event->status,
                    'venue' => $event->relationLoaded('venue') && $event->venue !== null
                        ? [
                            'id' => $event->venue->id,
                            'unique_id' => $event->venue->unique_id,
                            'name' => $event->venue->name,
                        ]
                        : null,
                ])
                ->values()
                ->all(),
        ];
    }
}
