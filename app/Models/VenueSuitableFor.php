<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venue_id
 * @property int $suitable_for_option_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'venue_id',
    'suitable_for_option_id',
])]
class VenueSuitableFor extends Model
{
    /**
     * @var string
     */
    protected $table = 'venue_suitable_for';

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<VenueSuitableForOption, $this>
     */
    public function suitableForOption(): BelongsTo
    {
        return $this->belongsTo(VenueSuitableForOption::class, 'suitable_for_option_id');
    }
}
