<?php

namespace App\Models;

use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property SiteBannerFeatureTypeEnum $feature_type
 * @property int|null $feature_target_id
 * @property SiteBannerFeatureImageTargetEnum $feature_image_target
 * @property string|null $custom_headline
 * @property string|null $image_url
 * @property string|null $cta_button_1_label
 * @property string|null $cta_button_1_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id',
    'feature_type',
    'feature_target_id',
    'feature_image_target',
    'custom_headline',
    'image_url',
    'cta_button_1_label',
    'cta_button_1_url',
])]
class SiteBanner extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature_type' => SiteBannerFeatureTypeEnum::class,
            'feature_target_id' => 'integer',
            'feature_image_target' => SiteBannerFeatureImageTargetEnum::class,
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
