<?php

namespace App\Services\Organisation;

use App\Enum\EventStatusEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteCategoryTypeEnum;
use App\Exceptions\ServiceException;
use App\Models\Event;
use App\Models\News;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\User;
use App\Services\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteCategoryService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
     * @return array{
     *     site: Site,
     *     feature_object: array{event_category: string, news_category: string, team_member_category: string},
     *     categories: Collection<int, SiteCategory>
     * }
     */
    public function show(User $actor, int $siteId): array
    {
        $organisationId = $this->organisationId($actor);
        $site = $this->resolveSite($organisationId, $siteId);

        $categories = SiteCategory::query()
            ->where('site_id', $site->id)
            ->orderBy('category_type')
            ->orderBy('category_id')
            ->get();

        return [
            'site' => $site,
            'feature_object' => $this->normalizeFeatureObject($site->feature_object),
            'categories' => $categories,
        ];
    }

    /**
     * Replace site feature toggles + selected landing-page records.
     *
     * @param  array{
     *     site_id: int,
     *     feature_object: array{event_category: string, news_category: string, team_member_category: string},
     *     categories?: list<array{category_type: string, category_id: int}>|null
     * }  $data
     * @return array{
     *     site: Site,
     *     feature_object: array{event_category: string, news_category: string, team_member_category: string},
     *     categories: Collection<int, SiteCategory>
     * }
     */
    public function update(User $actor, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $site = $this->resolveSite($organisationId, (int) $data['site_id']);

        $featureObject = $this->normalizeFeatureObject($data['feature_object'] ?? null);
        $rows = $this->normalizeCategoryRows($data['categories'] ?? []);
        $this->assertCategoryTargets($organisationId, (int) $site->id, $rows);

        try {
            DB::transaction(function () use ($site, $featureObject, $rows): void {
                $site->feature_object = $featureObject;
                $site->save();

                SiteCategory::query()->where('site_id', $site->id)->delete();

                if ($rows === []) {
                    return;
                }

                $now = now();
                SiteCategory::query()->insert(
                    array_map(
                        static fn (array $row): array => [
                            'site_id' => $site->id,
                            'category_type' => $row['category_type'],
                            'category_id' => $row['category_id'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                        $rows,
                    ),
                );
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_category_update_failed'),
                $exception->getMessage(),
            );
        }

        $site->refresh();

        $categories = SiteCategory::query()
            ->where('site_id', $site->id)
            ->orderBy('category_type')
            ->orderBy('category_id')
            ->get();

        return [
            'site' => $site,
            'feature_object' => $this->normalizeFeatureObject($site->feature_object),
            'categories' => $categories,
        ];
    }

    private function organisationId(User $actor): int
    {
        return (int) $this->organisation->getOrganisationDetail($actor)['organisation']->id;
    }

    private function resolveSite(int $organisationId, int $siteId): Site
    {
        $site = Site::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($siteId)
            ->first();

        if ($site === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_not_found'),
            );
        }

        return $site;
    }

    /**
     * @param  mixed  $value
     * @return array{event_category: string, news_category: string, team_member_category: string}
     */
    private function normalizeFeatureObject(mixed $value): array
    {
        $payload = is_array($value) ? $value : [];

        return [
            'event_category' => ($payload['event_category'] ?? 'off') === 'on' ? 'on' : 'off',
            'news_category' => ($payload['news_category'] ?? 'off') === 'on' ? 'on' : 'off',
            'team_member_category' => ($payload['team_member_category'] ?? 'off') === 'on' ? 'on' : 'off',
        ];
    }

    /**
     * Deduplicate category rows.
     *
     * @param  mixed  $categories
     * @return list<array{category_type: string, category_id: int}>
     */
    private function normalizeCategoryRows(mixed $categories): array
    {
        if (! is_array($categories)) {
            return [];
        }

        $seen = [];
        $rows = [];

        foreach ($categories as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = SiteCategoryTypeEnum::tryFrom((string) ($item['category_type'] ?? ''));
            $id = isset($item['category_id']) ? (int) $item['category_id'] : 0;

            if ($type === null || $id < 1) {
                continue;
            }

            $key = $type->value.':'.$id;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $rows[] = [
                'category_type' => $type->value,
                'category_id' => $id,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{category_type: string, category_id: int}>  $rows
     */
    private function assertCategoryTargets(int $organisationId, int $siteId, array $rows): void
    {
        $eventIds = [];
        $newsIds = [];

        foreach ($rows as $row) {
            if ($row['category_type'] === SiteCategoryTypeEnum::EVENTS->value) {
                $eventIds[] = $row['category_id'];
            }
            if ($row['category_type'] === SiteCategoryTypeEnum::NEWS->value) {
                $newsIds[] = $row['category_id'];
            }
        }

        $eventIds = array_values(array_unique($eventIds));
        $newsIds = array_values(array_unique($newsIds));

        if ($eventIds !== []) {
            $validCount = Event::query()
                ->where('organisation_id', $organisationId)
                ->where('status', EventStatusEnum::PUBLISHED)
                ->whereIn('id', $eventIds)
                ->count();

            if ($validCount !== count($eventIds)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.site_category_event_invalid'),
                );
            }
        }

        if ($newsIds !== []) {
            $validCount = News::query()
                ->where('organisation_id', $organisationId)
                // ->where('site_id', $siteId)
                ->where('status', NewsStatusEnum::PUBLISHED)
                ->whereIn('id', $newsIds)
                ->count();

            if ($validCount !== count($newsIds)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.site_category_news_invalid'),
                );
            }
        }
    }
}
