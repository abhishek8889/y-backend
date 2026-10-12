<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $event_id
 * @property int $organisation_id
 * @property int $event_ticket_id
 * @property int|null $ticket_offer_id
 * @property string $ticket_name
 * @property string|null $offer_name
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount_amount
 * @property string $total_price
 * @property string $platform_fee_rate
 * @property string $platform_share_amount
 * @property string $organiser_share_amount
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id',
    'event_id',
    'organisation_id',
    'event_ticket_id',
    'ticket_offer_id',
    'ticket_name',
    'offer_name',
    'quantity',
    'unit_price',
    'discount_amount',
    'total_price',
    'platform_fee_rate',
    'platform_share_amount',
    'organiser_share_amount',
    'currency',
])]
class OrderItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'platform_fee_rate' => 'decimal:2',
            'platform_share_amount' => 'decimal:2',
            'organiser_share_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<EventTicket, $this>
     */
    public function eventTicket(): BelongsTo
    {
        return $this->belongsTo(EventTicket::class);
    }

    /**
     * @return BelongsTo<EventTicketOffer, $this>
     */
    public function ticketOffer(): BelongsTo
    {
        return $this->belongsTo(EventTicketOffer::class, 'ticket_offer_id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
