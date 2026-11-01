<?php

namespace App\Models;

use App\Enum\SiteStatusEnum;
use App\Enum\SiteTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property int|null $organisation_id
 * @property int|null $site_template_id
 * @property SiteTypeEnum $site_type
 * @property string $name
 * @property string|null $slug
 * @property SiteStatusEnum $status
 * @property string|null $logo
 * @property string|null $favicon
 * @property array<string, mixed>|null $header
 * @property array<string, mixed>|null $footer
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'organisation_id',
    'site_template_id',
    'site_type',
    'name',
    'slug',
    'status',
    'logo',
    'favicon',
    'header',
    'footer',
    'created_by',
    'updated_by',
])]
class Site extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'site_type' => SiteTypeEnum::class,
            'status' => SiteStatusEnum::class,
            'header' => 'array',
            'footer' => 'array',
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
     * @return BelongsTo<SiteTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(SiteTemplate::class, 'site_template_id');
    }

    /**
     * @return HasOne<SiteDomain, $this>
     */
    public function domain(): HasOne
    {
        return $this->hasOne(SiteDomain::class);
    }

    /**
     * @return HasMany<SiteBanner, $this>
     */
    public function banners(): HasMany
    {
        return $this->hasMany(SiteBanner::class);
    }

    /**
     * @return HasMany<SiteContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(SiteContent::class);
    }

    /**
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'site_events')
            ->withPivot(['id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return BelongsToMany<News, $this>
     */
    public function news(): BelongsToMany
    {
        return $this->belongsToMany(News::class, 'site_news')
            ->withPivot(['id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
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
     * Branding is complete when logo, favicon, header and footer are all saved.
     */
    public function isBrandingComplete(): bool
    {
        if (! filled($this->logo) || ! filled($this->favicon)) {
            return false;
        }

        if (! is_array($this->header) || $this->header === []) {
            return false;
        }

        if (! is_array($this->footer) || $this->footer === []) {
            return false;
        }

        return true;
    }
}
