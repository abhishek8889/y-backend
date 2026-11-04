<?php

namespace App\Models;

use App\Enum\NewsStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $site_id
 * @property string|null $featured_image
 * @property string $headline
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $description
 * @property int|null $linked_event_id
 * @property NewsStatusEnum $status
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organisation_id',
    'site_id',
    'featured_image',
    'headline',
    'slug',
    'excerpt',
    'description',
    'linked_event_id',
    'status',
    'published_at',
    'created_by',
])]
class News extends Model
{
    protected $table = 'news';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NewsStatusEnum::class,
            'published_at' => 'datetime',
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
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function linkedEvent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'linked_event_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
