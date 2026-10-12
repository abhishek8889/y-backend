<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $currency
 * @property string $total_earned
 * @property string $total_paid
 * @property string $total_reserved
 * @property string $available_balance
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organisation_id',
    'currency',
    'total_earned',
    'total_paid',
    'total_reserved',
    'available_balance',
])]
class OrganiserAccount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_earned' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_reserved' => 'decimal:2',
            'available_balance' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
