<?php

namespace App\Models;

use App\Enum\IssuedTicketStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property string $code
 * @property int $order_id
 * @property int $order_item_id
 * @property int $organisation_id
 * @property int $event_id
 * @property int $event_ticket_id
 * @property int|null $ticket_offer_id
 * @property string $ticket_name
 * @property string|null $offer_name
 * @property IssuedTicketStatusEnum $status
 * @property Carbon|null $status_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'code',
    'order_id',
    'order_item_id',
    'organisation_id',
    'event_id',
    'event_ticket_id',
    'ticket_offer_id',
    'ticket_name',
    'offer_name',
    'status',
    'status_updated_at',
])]
class IssuedTicket extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IssuedTicketStatusEnum::class,
            'status_updated_at' => 'datetime',
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
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
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
}
