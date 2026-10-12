<?php

namespace App\Models;

use App\Enum\OrganiserLedgerEntryTypeEnum;
use App\Enum\OrganiserLedgerReferenceTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property OrganiserLedgerEntryTypeEnum $entry_type
 * @property OrganiserLedgerReferenceTypeEnum $reference_type
 * @property int $reference_id
 * @property string $credit_amount
 * @property string $debit_amount
 * @property string $currency
 * @property string $idempotency_key
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 */
#[Fillable([
    'organisation_id',
    'entry_type',
    'reference_type',
    'reference_id',
    'credit_amount',
    'debit_amount',
    'currency',
    'idempotency_key',
    'description',
    'metadata',
])]
class OrganiserLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => OrganiserLedgerEntryTypeEnum::class,
            'reference_type' => OrganiserLedgerReferenceTypeEnum::class,
            'reference_id' => 'integer',
            'credit_amount' => 'decimal:2',
            'debit_amount' => 'decimal:2',
            'metadata' => 'array',
            'created_at' => 'datetime',
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
