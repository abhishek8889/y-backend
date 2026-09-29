<?php

namespace App\Models;

use App\Enum\StripeOnboardingStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $stripe_account_id
 * @property string $account_type
 * @property string $country
 * @property string $default_currency
 * @property string|null $business_type
 * @property string|null $email
 * @property bool $charges_enabled
 * @property bool $payouts_enabled
 * @property bool $details_submitted
 * @property StripeOnboardingStatusEnum $onboarding_status
 * @property array<int, mixed>|null $requirements_currently_due
 * @property array<int, mixed>|null $requirements_past_due
 * @property string|null $requirements_disabled_reason
 * @property Carbon|null $onboarding_completed_at
 * @property Carbon|null $last_synced_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organisation_id',
    'stripe_account_id',
    'account_type',
    'country',
    'default_currency',
    'business_type',
    'email',
    'charges_enabled',
    'payouts_enabled',
    'details_submitted',
    'onboarding_status',
    'requirements_currently_due',
    'requirements_past_due',
    'requirements_disabled_reason',
    'onboarding_completed_at',
    'last_synced_at',
    'metadata',
])]
class OrganisationStripeAccount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charges_enabled' => 'boolean',
            'payouts_enabled' => 'boolean',
            'details_submitted' => 'boolean',
            'onboarding_status' => StripeOnboardingStatusEnum::class,
            'requirements_currently_due' => 'array',
            'requirements_past_due' => 'array',
            'onboarding_completed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
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
     * @return HasMany<OrganisationStripeExternalAccount, $this>
     */
    public function externalAccounts(): HasMany
    {
        return $this->hasMany(OrganisationStripeExternalAccount::class);
    }
}
