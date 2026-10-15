<?php

namespace App\Models;

use App\Enum\RefundStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property int $order_id
 * @property int $payment_id
 * @property int|null $order_item_id
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property string|null $stripe_refund_id
 * @property string $amount
 * @property string $currency
 * @property string|null $reason
 * @property RefundStatusEnum $status
 * @property string|null $failure_reason
 * @property Carbon|null $refunded_at
 * @property Carbon|null $customer_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'order_id',
    'payment_id',
    'order_item_id',
    'requested_by',
    'approved_by',
    'stripe_refund_id',
    'amount',
    'currency',
    'reason',
    'status',
    'failure_reason',
    'refunded_at',
    'customer_notified_at',
])]
class Refund extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundStatusEnum::class,
            'refunded_at' => 'datetime',
            'customer_notified_at' => 'datetime',
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
