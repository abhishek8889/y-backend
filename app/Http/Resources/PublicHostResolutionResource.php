<?php

namespace App\Http\Resources;

use App\Enum\MediaTypeEnum;
use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use App\Models\Event;
use App\Models\News;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\SiteBanner;
use App\Models\SiteDomain;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property array{
 *     host_type: 'public'|'microsite',
 *     hostname: string,
 *     organisation: Organisation|null,
 *     site: Site|null,
 *     domain: SiteDomain|null,
 *     feature_events?: Collection<int, Event>,
 *     feature_news?: Collection<int, News>
 * } $resource
 */
class PublicHostResolutionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{
         *     host_type: string,
         *     hostname: string,
         *     organisation: Organisation|null,
         *     site: Site|null,
         *     domain: SiteDomain|null,
         *     feature_events?: Collection<int, Event>,
         *     feature_news?: Collection<int, News>
         * } $data
         */
        $data = $this->resource;

        $hostType = $data['host_type'];
        $site = $data['site'];
        $organisation = $data['organisation'];
        $domain = $data['domain'];
        /** @var Collection<int, Event> $featureEvents */
        $featureEvents = $data['feature_events'] ?? collect();
        /** @var Collection<int, News> $featureNews */
        $featureNews = $data['feature_news'] ?? collect();

        if ($hostType === 'public') {
            return [
                'host_type' => 'public',
                'hostname' => $data['hostname'],
                'organisation' => null,
                'site' => null,
                'domain' => null,
                'banners' => [],
            ];
        }

        return [
            'host_type' => 'microsite',
            'hostname' => $data['hostname'],
            'organisation' => $organisation === null
                ? null
                : [
                    'id' => $organisation->id,
                    'unique_id' => $organisation->unique_id,
                    'name' => $organisation->name,
                    'logo' => $this->mediaAsset($organisation->logo),
                ],
            'site' => $site === null
                ? null
                : [
                    'id' => $site->id,
                    'unique_id' => $site->unique_id,
                    'name' => $site->name,
                    'slug' => $site->slug,
                    'status' => $site->status?->value ?? $site->status,
                    'logo' => $this->mediaAsset($site->logo),
                    'navigation' => is_array($site->navigation)
                        ? array_values($site->navigation)
                        : [],
                    'feature_object' => is_array($site->feature_object)
                        ? $site->feature_object
                        : [
                            'event_category' => 'off',
                            'news_category' => 'off',
                        ],
                ],
            'domain' => $domain === null
                ? null
                : [
                    'hostname' => $domain->hostname,
                    'status' => $domain->status?->value ?? $domain->status,
                ],
            'banners' => $site === null || ! $site->relationLoaded('banners')
                ? []
                : $site->banners
                    ->map(fn (SiteBanner $banner): array => $this->mapBanner(
                        $banner,
                        $featureEvents,
                        $featureNews,
                    ))
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @param  Collection<int, Event>  $featureEvents
     * @param  Collection<int, News>  $featureNews
     * @return array<string, mixed>
     */
    private function mapBanner(
        SiteBanner $banner,
        Collection $featureEvents,
        Collection $featureNews,
    ): array {
        $media = $this->resolveBannerMedia($banner, $featureEvents, $featureNews);

        return [
            'id' => $banner->id,
            'feature_type' => $banner->feature_type?->value ?? $banner->feature_type,
            'feature_target_id' => $banner->feature_target_id,
            'feature_image_target' => $banner->feature_image_target?->value
                ?? $banner->feature_image_target,
            'custom_headline' => $banner->custom_headline,
            'image_url' => $media['image_url'],
            'media_type' => $media['media_type'],
            'cta_button_1_label' => $banner->cta_button_1_label,
            'cta_button_1_url' => $banner->cta_button_1_url,
        ];
    }

    /**
     * @param  Collection<int, Event>  $featureEvents
     * @param  Collection<int, News>  $featureNews
     * @return array{image_url: string|null, media_type: string|null}
     */
    private function resolveBannerMedia(
        SiteBanner $banner,
        Collection $featureEvents,
        Collection $featureNews,
    ): array {
        $imageTarget = $banner->feature_image_target;

        if ($imageTarget === SiteBannerFeatureImageTargetEnum::FROM_FEATURE) {
            return $this->resolveFromFeature($banner, $featureEvents, $featureNews);
        }

        $path = is_string($banner->image_url) ? trim($banner->image_url) : '';

        if ($path === '') {
            return [
                'image_url' => null,
                'media_type' => null,
            ];
        }

        return [
            'image_url' => $this->resolveMediaUrl($path),
            'media_type' => MediaTypeEnum::fromPath($path)->value,
        ];
    }

    /**
     * @param  Collection<int, Event>  $featureEvents
     * @param  Collection<int, News>  $featureNews
     * @return array{image_url: string|null, media_type: string|null}
     */
    private function resolveFromFeature(
        SiteBanner $banner,
        Collection $featureEvents,
        Collection $featureNews,
    ): array {
        $targetId = $banner->feature_target_id;

        if ($targetId === null) {
            return [
                'image_url' => null,
                'media_type' => null,
            ];
        }

        if ($banner->feature_type === SiteBannerFeatureTypeEnum::EVENTS) {
            /** @var Event|null $event */
            $event = $featureEvents->get((int) $targetId);
            $image = $event?->images?->first();

            if ($image === null || ! is_string($image->path) || trim($image->path) === '') {
                return [
                    'image_url' => null,
                    'media_type' => null,
                ];
            }

            $path = trim($image->path);

            return [
                'image_url' => $this->resolveMediaUrl($path),
                'media_type' => $image->media_type?->value
                    ?? MediaTypeEnum::fromPath($path)->value,
            ];
        }

        if ($banner->feature_type === SiteBannerFeatureTypeEnum::NEWS) {
            /** @var News|null $news */
            $news = $featureNews->get((int) $targetId);
            $path = is_string($news?->featured_image) ? trim($news->featured_image) : '';

            if ($path === '') {
                return [
                    'image_url' => null,
                    'media_type' => null,
                ];
            }

            return [
                'image_url' => $this->resolveMediaUrl($path),
                'media_type' => MediaTypeEnum::fromPath($path)->value,
            ];
        }

        return [
            'image_url' => null,
            'media_type' => null,
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

    private function resolveMediaUrl(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $trimmed = trim($path);

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return app(MediaService::class)->url($trimmed);
    }
}
