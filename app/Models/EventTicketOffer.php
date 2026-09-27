<?php

namespace App\Models;

use App\Enum\EventTicketOfferStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_ticket_id
 * @property string $unique_id
 * @property string $name
 * @property string $price
 * @property int $quantity_cap
 * @property Carbon|null $sale_starts_at
 * @property Carbon|null $sale_ends_at
 * @property int|null $max_per_order
 * @property string $access
 * @property EventTicketOfferStatusEnum $status
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'event_ticket_id',
    'unique_id',
    'name',
    'price',
    'quantity_cap',
    'sale_starts_at',
    'sale_ends_at',
    'max_per_order',
    'access',
    'status',
    'sort_order',
    'created_by',
    'updated_by',
])]
class EventTicketOffer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity_cap' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
            'max_per_order' => 'integer',
            'sort_order' => 'integer',
            'status' => EventTicketOfferStatusEnum::class,
        ];
    }

    /**
     * @return BelongsTo<EventTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(EventTicket::class, 'event_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
