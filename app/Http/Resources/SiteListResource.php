<?php

namespace App\Http\Resources;

use App\Models\Site;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'organisation_id' => $this->organisation_id,
            'site_template_id' => $this->site_template_id,
            'site_type' => $this->site_type?->value ?? $this->site_type,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value ?? $this->status,
            'logo' => $this->mediaAsset($this->logo),
            'favicon' => $this->mediaAsset($this->favicon),
            'header' => $this->withResolvedMedia($this->header),
            'footer' => $this->withResolvedMedia($this->footer),
            'branding_complete' => $this->isBrandingComplete(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'domain' => $this->when(
                $this->relationLoaded('domain'),
                fn () => $this->domain === null
                    ? null
                    : SiteDomainResource::make($this->domain),
            ),
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

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function withResolvedMedia(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (array_key_exists('logo', $payload)) {
            $logo = $payload['logo'] ?? null;
            if (is_string($logo) && trim($logo) !== '') {
                $payload['logo'] = $this->mediaAsset($logo);
            } elseif (is_array($logo) && isset($logo['path']) && is_string($logo['path'])) {
                $payload['logo'] = $this->mediaAsset($logo['path']);
            } else {
                $payload['logo'] = null;
            }
        }

        return $payload;
    }
}
