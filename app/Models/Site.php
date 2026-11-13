<?php

namespace App\Models;

use App\Enum\SiteStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $unique_id
 * @property int|null $organisation_id
 * @property int|null $site_template_id
 * @property string $name
 * @property string|null $slug
 * @property SiteStatusEnum $status
 * @property string|null $logo
 * @property array<string, mixed>|null $navigation
 * @property array<string, mixed>|null $feature_object
 * @property array<string, string>|null $social_links
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'unique_id',
    'organisation_id',
    'site_template_id',
    'name',
    'slug',
    'status',
    'logo',
    'navigation',
    'feature_object',
    'social_links',
    'created_by',
])]
class Site extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SiteStatusEnum::class,
            'navigation' => 'array',
            'feature_object' => 'array',
            'social_links' => 'array',
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
     * @return HasMany<SiteCategory, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(SiteCategory::class);
    }

    /**
     * @return HasMany<SiteSupportContact, $this>
     */
    public function supportContacts(): HasMany
    {
        return $this->hasMany(SiteSupportContact::class);
    }

    /**
     * @return HasMany<News, $this>
     */
    public function newsItems(): HasMany
    {
        return $this->hasMany(News::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Branding is complete when logo and navigation are saved.
     */
    public function isBrandingComplete(): bool
    {
        if (! filled($this->logo)) {
            return false;
        }

        if (! is_array($this->navigation) || $this->navigation === []) {
            return false;
        }

        return true;
    }
}
