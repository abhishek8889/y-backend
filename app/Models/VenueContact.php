<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $type
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $alternative_phone
 * @property string|null $website
 * @property string|null $facebook_url
 * @property string|null $instagram_url
 * @property string|null $twitter_url
 * @property string|null $youtube_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'venue_id',
    'type',
    'name',
    'email',
    'phone',
    'alternative_phone',
    'website',
    'facebook_url',
    'instagram_url',
    'twitter_url',
    'youtube_url',
])]
class VenueContact extends Model
{
    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
