<?php

namespace App\Models;

use App\Enum\SiteBannerStatusEnum;
use App\Enum\SiteBannerTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property SiteBannerTypeEnum $banner_type
 * @property string|null $banner_url
 * @property string|null $banner_title
 * @property string|null $banner_subtitle
 * @property string|null $cta_button_1_label
 * @property string|null $cta_button_1_url
 * @property string|null $cta_button_2_label
 * @property string|null $cta_button_2_url
 * @property SiteBannerStatusEnum $status
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id',
    'banner_type',
    'banner_url',
    'banner_title',
    'banner_subtitle',
    'cta_button_1_label',
    'cta_button_1_url',
    'cta_button_2_label',
    'cta_button_2_url',
    'status',
    'sort_order',
])]
class SiteBanner extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'banner_type' => SiteBannerTypeEnum::class,
            'status' => SiteBannerStatusEnum::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
