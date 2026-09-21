<?php

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $unique_id
 * @property string|null $organiser_name
 * @property string $name
 * @property string|null $email
 * @property string|null $country_code
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $city
 * @property string|null $address1
 * @property string|null $address2
 * @property string|null $postal_code
 * @property string|null $website
 * @property string|null $logo
 * @property string|null $banner
 * @property string|null $description
 * @property string|null $keywords
 * @property string|null $facebook_link
 * @property string|null $instagram_link
 * @property string|null $twitter_link
 * @property string|null $youtube_link
 * @property bool $complete_status
 * @property bool $approve_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'owner_id',
    'unique_id',
    'organiser_name',
    'name',
    'email',
    'country_code',
    'phone',
    'country',
    'city',
    'address1',
    'address2',
    'postal_code',
    'website',
    'logo',
    'banner',
    'description',
    'keywords',
    'facebook_link',
    'instagram_link',
    'twitter_link',
    'youtube_link',
    'complete_status',
    'approve_status',
])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'complete_status' => 'boolean',
            'approve_status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<OrganiserStaff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(OrganiserStaff::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
