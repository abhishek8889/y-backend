<?php

namespace App\Models;

use App\Enum\OrderStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property string $checkout_key
 * @property int|null $user_id
 * @property int|null $coupon_id
 * @property string|null $coupon_code
 * @property OrderStatusEnum $status
 * @property string $subtotal
 * @property string $discount_amount
 * @property string $platform_fee_amount
 * @property string $total_amount
 * @property string $currency
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $confirmation_email_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'checkout_key',
    'user_id',
    'coupon_id',
    'coupon_code',
    'status',
    'subtotal',
    'discount_amount',
    'platform_fee_amount',
    'total_amount',
    'currency',
    'customer_name',
    'customer_email',
    'customer_phone',
    'expires_at',
    'paid_at',
    'cancelled_at',
    'confirmation_email_sent_at',
])]
class Order extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatusEnum::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'platform_fee_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'confirmation_email_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
