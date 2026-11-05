<?php

namespace App\Http\Resources;

use App\Models\SiteBanner;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteBanner
 */
class SiteBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'feature_type' => $this->feature_type?->value ?? $this->feature_type,
            'feature_target_id' => $this->feature_target_id,
            'feature_image_target' => $this->feature_image_target?->value
                ?? $this->feature_image_target,
            'custom_headline' => $this->custom_headline,
            'image_url' => $this->mediaAsset($this->image_url),
            'cta_button_1_label' => $this->cta_button_1_label,
            'cta_button_1_url' => $this->cta_button_1_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{path: string, url: string}|null
     */
    private function mediaAsset(mixed $path): ?array
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $trimmed = trim($path);

        return [
            'path' => $trimmed,
            'url' => app(MediaService::class)->url($trimmed),
        ];
    }
}
