<?php

namespace App\Models;

use App\Enum\EventTicketStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_id
 * @property string $unique_id
 * @property string $name
 * @property string|null $description
 * @property string $base_price
 * @property string $currency
 * @property int $quantity_cap
 * @property string|null $badge_label
 * @property string|null $entitlement
 * @property EventTicketStatusEnum $status
 * @property int $sort_order
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'event_id',
    'unique_id',
    'name',
    'description',
    'base_price',
    'currency',
    'quantity_cap',
    'badge_label',
    'entitlement',
    'status',
    'sort_order',
    'created_by',
    'updated_by',
])]
class EventTicket extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'quantity_cap' => 'integer',
            'sort_order' => 'integer',
            'status' => EventTicketStatusEnum::class,
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<EventTicketOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(EventTicketOffer::class)->orderBy('sort_order');
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
