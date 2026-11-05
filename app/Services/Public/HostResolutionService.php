<?php

namespace App\Services\Public;

use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use App\Enum\SiteStatusEnum;
use App\Models\Event;
use App\Models\News;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\Service;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class HostResolutionService extends Service
{
    /**
     * Resolve a customer-facing hostname to marketplace public or published microsite.
     *
     * @return array{
     *     host_type: 'public'|'microsite',
     *     hostname: string,
     *     organisation: \App\Models\Organisation|null,
     *     site: \App\Models\Site|null,
     *     domain: SiteDomain|null,
     *     feature_events: Collection<int, Event>,
     *     feature_news: Collection<int, News>
     * }
     */
    public function resolve(string $rawHostname): array
    {
        $hostname = $this->normalizeHostname($rawHostname);

        if ($hostname === '') {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('validation.required', ['attribute' => 'hostname']),
            );
        }

        if ($this->isMarketplaceHost($hostname)) {
            return [
                'host_type' => 'public',
                'hostname' => $hostname,
                'organisation' => null,
                'site' => null,
                'domain' => null,
                'feature_events' => collect(),
                'feature_news' => collect(),
            ];
        }

        $domain = SiteDomain::query()
            ->where('hostname', $hostname)
            ->with([
                'site.organisation',
                'site.banners' => fn ($query) => $query->orderBy('id'),
            ])
            ->first();

        if ($domain === null || $domain->site === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.public_host_not_found'),
            );
        }

        $site = $domain->site;

        if ($site->status !== SiteStatusEnum::ACTIVE) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.public_host_not_found'),
            );
        }

        $featureMedia = $this->loadBannerFeatureMedia($site);

        return [
            'host_type' => 'microsite',
            'hostname' => $hostname,
            'organisation' => $site->organisation,
            'site' => $site,
            'domain' => $domain,
            'feature_events' => $featureMedia['events'],
            'feature_news' => $featureMedia['news'],
        ];
    }

    /**
     * Prefetch event/news targets used by from_feature banners.
     *
     * @return array{events: Collection<int, Event>, news: Collection<int, News>}
     */
    private function loadBannerFeatureMedia(Site $site): array
    {
        $banners = $site->relationLoaded('banners') ? $site->banners : collect();

        $fromFeature = $banners->filter(
            fn ($banner) => $banner->feature_image_target === SiteBannerFeatureImageTargetEnum::FROM_FEATURE
                && $banner->feature_target_id !== null,
        );

        $eventIds = $fromFeature
            ->filter(fn ($banner) => $banner->feature_type === SiteBannerFeatureTypeEnum::EVENTS)
            ->pluck('feature_target_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $newsIds = $fromFeature
            ->filter(fn ($banner) => $banner->feature_type === SiteBannerFeatureTypeEnum::NEWS)
            ->pluck('feature_target_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $events = $eventIds === []
            ? collect()
            : Event::query()
                ->whereIn('id', $eventIds)
                ->with([
                    'images' => fn ($query) => $query
                        ->where('type', 'banner')
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->get()
                ->keyBy('id');

        $news = $newsIds === []
            ? collect()
            : News::query()
                ->whereIn('id', $newsIds)
                ->get()
                ->keyBy('id');

        return [
            'events' => $events,
            'news' => $news,
        ];
    }

    public function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));
        $hostname = preg_replace('#^https?://#i', '', $hostname) ?? $hostname;

        // Strip path / query / fragment if a partial URL was passed without scheme.
        $hostname = explode('/', $hostname, 2)[0];
        $hostname = explode('?', $hostname, 2)[0];
        $hostname = explode('#', $hostname, 2)[0];

        // Strip port.
        if (str_contains($hostname, ':')) {
            $hostname = explode(':', $hostname, 2)[0];
        }

        $hostname = rtrim($hostname, '.');

        return $hostname;
    }

    public function isMarketplaceHost(string $hostname): bool
    {
        $hosts = array_map(
            fn (string $host): string => $this->normalizeHostname($host),
            config('hosts.marketplace', []),
        );
        $hosts = array_values(array_filter($hosts));

        if (in_array($hostname, $hosts, true)) {
            return true;
        }

        if (! config('hosts.equate_www', true)) {
            return false;
        }

        $candidates = [$hostname];
        if (str_starts_with($hostname, 'www.')) {
            $candidates[] = substr($hostname, 4);
        } else {
            $candidates[] = 'www.'.$hostname;
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $hosts, true)) {
                return true;
            }
        }

        return false;
    }
}
