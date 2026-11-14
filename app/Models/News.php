<?php

namespace App\Models;

use App\Enum\NewsStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int|null $site_id
 * @property string|null $featured_image
 * @property string|null $author
 * @property string|null $content_role
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
    'author',
    'content_role',
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
     * Publicly visible news:
     * - published (optionally gated by published_at)
     * - scheduled whose published_at has already passed (cron lag safety)
     *
     * @param  Builder<News>  $query
     * @return Builder<News>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        $now = now();

        return $query->where(function (Builder $outer) use ($now): void {
            $outer->where(function (Builder $published) use ($now): void {
                $published->where('status', NewsStatusEnum::PUBLISHED)
                    ->where(function (Builder $when) use ($now): void {
                        $when->whereNull('published_at')
                            ->orWhere('published_at', '<=', $now);
                    });
            })->orWhere(function (Builder $scheduled) use ($now): void {
                $scheduled->where('status', NewsStatusEnum::SCHEDULED)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', $now);
            });
        });
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
