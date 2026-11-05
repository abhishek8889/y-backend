<?php

namespace App\Services\Organisation;

use App\Enum\EventStatusEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteBannerFeatureImageTargetEnum;
use App\Enum\SiteBannerFeatureTypeEnum;
use App\Exceptions\ServiceException;
use App\Models\Event;
use App\Models\News;
use App\Models\Site;
use App\Models\SiteBanner;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteBannerService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly MediaService $media,
    ) {}

    /**
     * @return array{banner: SiteBanner|null}
     */
    public function show(User $actor, int $siteId): array
    {
        $organisationId = $this->organisationId($actor);
        $this->assertSiteBelongsToOrganisation($organisationId, $siteId);

        $banner = SiteBanner::query()
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->first();

        return [
            'banner' => $banner,
        ];
    }

    /**
     * Upsert the homepage banner for a site (one active row per site).
     *
     * @param  array{
     *     site_id: int,
     *     feature_type: string,
     *     feature_target_id?: int|null,
     *     feature_image_target: string,
     *     custom_headline?: string|null,
     *     image_url?: string|null,
     *     cta_button_1_label?: string|null,
     *     cta_button_1_url?: string|null
     * }  $data
     * @return array{banner: SiteBanner}
     */
    public function save(User $actor, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $siteId = $this->assertSiteBelongsToOrganisation($organisationId, (int) $data['site_id']);

        $featureType = SiteBannerFeatureTypeEnum::from((string) $data['feature_type']);
        $imageTarget = SiteBannerFeatureImageTargetEnum::from((string) $data['feature_image_target']);

        $featureTargetId = $this->resolveFeatureTargetId(
            $organisationId,
            $siteId,
            $featureType,
            $data['feature_target_id'] ?? null,
        );

        if ($featureType === SiteBannerFeatureTypeEnum::CUSTOM) {
            $imageTarget = SiteBannerFeatureImageTargetEnum::CUSTOM_UPLOAD;
            $featureTargetId = null;
        }

        $customHeadline = $featureType === SiteBannerFeatureTypeEnum::CUSTOM
            ? (filled($data['custom_headline'] ?? null) ? trim((string) $data['custom_headline']) : null)
            : null;

        $existing = SiteBanner::query()
            ->where('site_id', $siteId)
            ->orderByDesc('id')
            ->first();

        $previousImage = is_string($existing?->image_url) ? $existing->image_url : null;

        try {
            $banner = DB::transaction(function () use (
                $actor,
                $siteId,
                $featureType,
                $featureTargetId,
                $imageTarget,
                $customHeadline,
                $data,
                $existing,
                $previousImage,
            ): SiteBanner {
                $banner = $existing ?? new SiteBanner(['site_id' => $siteId]);
                $banner->site_id = $siteId;
                $banner->feature_type = $featureType;
                $banner->feature_target_id = $featureTargetId;
                $banner->feature_image_target = $imageTarget;
                $banner->custom_headline = $customHeadline;
                $banner->cta_button_1_label = filled($data['cta_button_1_label'] ?? null)
                    ? trim((string) $data['cta_button_1_label'])
                    : null;
                $banner->cta_button_1_url = filled($data['cta_button_1_url'] ?? null)
                    ? trim((string) $data['cta_button_1_url'])
                    : null;

                if ($imageTarget === SiteBannerFeatureImageTargetEnum::FROM_FEATURE) {
                    if ($previousImage !== null && $previousImage !== '') {
                        $this->media->deleteStoredFile($previousImage);
                    }
                    $banner->image_url = null;
                } else {
                    $banner->image_url = $this->resolveBannerImagePath(
                        $actor,
                        $siteId,
                        $data['image_url'] ?? null,
                        $previousImage,
                    );
                }

                $banner->save();

                return $banner->fresh() ?? $banner;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_banner_save_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'banner' => $banner,
        ];
    }

    private function organisationId(User $actor): int
    {
        return (int) $this->organisation->getOrganisationDetail($actor)['organisation']->id;
    }

    private function assertSiteBelongsToOrganisation(int $organisationId, int $siteId): int
    {
        $exists = Site::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($siteId)
            ->exists();

        if (! $exists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_not_found'),
            );
        }

        return $siteId;
    }

    private function resolveFeatureTargetId(
        int $organisationId,
        int $siteId,
        SiteBannerFeatureTypeEnum $featureType,
        mixed $featureTargetId,
    ): ?int {
        if ($featureType === SiteBannerFeatureTypeEnum::CUSTOM) {
            return null;
        }

        if ($featureTargetId === null || $featureTargetId === '') {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('validation.required', ['attribute' => 'feature target']),
            );
        }

        $id = (int) $featureTargetId;

        if ($featureType === SiteBannerFeatureTypeEnum::EVENTS) {
            $eventExists = Event::query()
                ->where('organisation_id', $organisationId)
                ->whereKey($id)
                ->where('status', EventStatusEnum::PUBLISHED)
                ->exists();

            if (! $eventExists) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.site_banner_event_invalid'),
                );
            }

            return $id;
        }

        $newsExists = News::query()
            ->where('organisation_id', $organisationId)
            ->where('site_id', $siteId)
            ->whereKey($id)
            ->where('status', NewsStatusEnum::PUBLISHED)
            ->exists();

        if (! $newsExists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_banner_news_invalid'),
            );
        }

        return $id;
    }

    private function resolveBannerImagePath(
        User $actor,
        int $siteId,
        mixed $value,
        ?string $previousPath,
    ): ?string {
        $path = $this->extractMediaPath($value);

        if ($path === null) {
            if (is_string($previousPath) && $previousPath !== '') {
                $this->media->deleteStoredFile($previousPath);
            }

            return null;
        }

        if ($previousPath !== null && $previousPath !== '' && $previousPath === $path) {
            return $previousPath;
        }

        if ($this->media->isTmpPath($path)) {
            $this->media->assertOwnedTmpFile($actor, $path);
            $moved = $this->media->moveOwnedTmpTo(
                $actor,
                $path,
                'sites/'.$siteId.'/banner',
            );
            $path = $moved['path'];
        }

        if (
            is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $path
        ) {
            $this->media->deleteStoredFile($previousPath);
        }

        return $path;
    }

    private function extractMediaPath(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_array($value) && isset($value['path']) && is_string($value['path'])) {
            $trimmed = trim($value['path']);

            return $trimmed !== '' ? $trimmed : null;
        }

        return null;
    }
}
