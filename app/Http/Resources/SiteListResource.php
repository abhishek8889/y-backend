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
        $domain = $this->relationLoaded('domain') ? $this->domain : null;

        return [
            'id' => $this->id,
            'unique_id' => $this->unique_id,
            'organisation_id' => $this->organisation_id,
            'site_template_id' => $this->site_template_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status?->value ?? $this->status,
            'logo' => $this->mediaAsset($this->logo),
            'navigation' => is_array($this->navigation) ? array_values($this->navigation) : [],
            'feature_object' => is_array($this->feature_object) ? $this->feature_object : [
                'event_category' => 'off',
                'news_category' => 'off',
            ],
            'social_links' => is_array($this->social_links) ? $this->social_links : (object) [],
            'hostname' => $domain?->hostname,
            'domain' => $domain === null
                ? null
                : [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'status' => $domain->status?->value ?? $domain->status,
                    'vercel_added' => (bool) $domain->vercel_added,
                    'dns_instructions' => $domain->dns_instructions,
                    'verified_at' => $domain->verified_at?->toIso8601String(),
                    'activated_at' => $domain->activated_at?->toIso8601String(),
                    'last_error' => $domain->last_error,
                ],
            'template' => $this->when(
                $this->relationLoaded('template'),
                fn () => $this->template === null
                    ? null
                    : [
                        'id' => $this->template->id,
                        'key' => $this->template->key,
                        'name' => $this->template->name,
                        'frontend_key' => $this->template->frontend_key,
                    ],
            ),
            'branding_complete' => $this->isBrandingComplete(),
            'created_by' => $this->created_by,
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
