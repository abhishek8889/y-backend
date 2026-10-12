<?php

namespace App\Models;

use App\Enum\CouponDiscountTypeEnum;
use App\Enum\CouponStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property int|null $organisation_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property CouponDiscountTypeEnum $discount_type
 * @property string $discount_value
 * @property string|null $currency
 * @property string|null $max_discount_amount
 * @property string|null $minimum_order_amount
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property CouponStatusEnum $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'organisation_id',
    'code',
    'name',
    'description',
    'discount_type',
    'discount_value',
    'currency',
    'max_discount_amount',
    'minimum_order_amount',
    'usage_limit',
    'usage_limit_per_user',
    'starts_at',
    'expires_at',
    'status',
    'created_by',
])]
class Coupon extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => CouponDiscountTypeEnum::class,
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => CouponStatusEnum::class,
        ];
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
