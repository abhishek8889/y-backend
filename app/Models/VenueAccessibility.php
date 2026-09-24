<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venue_id
 * @property bool|null $accessible_entrance
 * @property bool|null $accessible_toilet
 * @property bool|null $wheelchair_access
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'venue_id',
    'accessible_entrance',
    'accessible_toilet',
    'wheelchair_access',
    'notes',
])]
class VenueAccessibility extends Model
{
    /**
     * @var string
     */
    protected $table = 'venue_accessibility';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessible_entrance' => 'boolean',
            'accessible_toilet' => 'boolean',
            'wheelchair_access' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
