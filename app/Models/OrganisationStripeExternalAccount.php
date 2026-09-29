<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_stripe_account_id
 * @property string $stripe_external_account_id
 * @property string $object
 * @property string|null $bank_name
 * @property string|null $last4
 * @property string|null $country
 * @property string|null $currency
 * @property string|null $status
 * @property bool $is_default_for_currency
 * @property string|null $fingerprint
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organisation_stripe_account_id',
    'stripe_external_account_id',
    'object',
    'bank_name',
    'last4',
    'country',
    'currency',
    'status',
    'is_default_for_currency',
    'fingerprint',
    'raw',
])]
class OrganisationStripeExternalAccount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_for_currency' => 'boolean',
            'raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<OrganisationStripeAccount, $this>
     */
    public function stripeAccount(): BelongsTo
    {
        return $this->belongsTo(OrganisationStripeAccount::class, 'organisation_stripe_account_id');
    }
}
