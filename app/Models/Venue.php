<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $unique_id
 * @property string $name
 * @property int|null $venue_type_id
 * @property string|null $description
 * @property int|null $maximum_capacity
 * @property int|null $standing_capacity
 * @property int|null $seated_capacity
 * @property string $status
 * @property bool $is_private_hire_available
 * @property string|null $private_hire_description
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organisation_id',
    'unique_id',
    'name',
    'venue_type_id',
    'description',
    'maximum_capacity',
    'standing_capacity',
    'seated_capacity',
    'status',
    'is_private_hire_available',
    'private_hire_description',
    'created_by',
    'updated_by',
])]
class Venue extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maximum_capacity' => 'integer',
            'standing_capacity' => 'integer',
            'seated_capacity' => 'integer',
            'is_private_hire_available' => 'boolean',
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
     * @return BelongsTo<VenueType, $this>
     */
    public function venueType(): BelongsTo
    {
        return $this->belongsTo(VenueType::class);
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

    /**
     * @return HasOne<VenueAddress, $this>
     */
    public function address(): HasOne
    {
        return $this->hasOne(VenueAddress::class);
    }

    /**
     * @return HasMany<VenueContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(VenueContact::class);
    }

    /**
     * @return HasMany<VenueImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(VenueImage::class);
    }

    /**
     * @return HasOne<VenueAccessibility, $this>
     */
    public function accessibility(): HasOne
    {
        return $this->hasOne(VenueAccessibility::class);
    }

    /**
     * @return HasOne<VenueLogistics, $this>
     */
    public function logistics(): HasOne
    {
        return $this->hasOne(VenueLogistics::class);
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'venue_facilities')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<VenueSuitableForOption, $this>
     */
    public function suitableForOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            VenueSuitableForOption::class,
            'venue_suitable_for',
            'venue_id',
            'suitable_for_option_id',
        )->withTimestamps();
    }
}
