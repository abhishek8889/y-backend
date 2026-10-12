<?php

namespace App\Models;

use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property PaymentProviderEnum $provider
 * @property string|null $payment_intent_id
 * @property string|null $checkout_session_id
 * @property string|null $charge_id
 * @property PaymentStatusEnum $status
 * @property string $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property string $idempotency_key
 * @property string|null $failure_reason
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'order_id',
    'provider',
    'payment_intent_id',
    'checkout_session_id',
    'charge_id',
    'status',
    'amount',
    'currency',
    'payment_method',
    'idempotency_key',
    'failure_reason',
    'paid_at',
])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProviderEnum::class,
            'status' => PaymentStatusEnum::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
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
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
