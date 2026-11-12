<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\NewsStatusEnum;
use App\Enum\SiteCategoryTypeEnum;
use App\Enum\SiteStatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\News;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\SiteSupportContact;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class MicrositeService extends Service
{
    /**
     * Published events selected on a microsite home (via site_categories).
     *
     * @param  array{site_unique_id: string, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function homeCategoryEvents(array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $eventIds = $this->categoryTargetIds($site->id, SiteCategoryTypeEnum::EVENTS);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Event::query()
            ->whereIn('id', $eventIds)
            ->where('organisation_id', $site->organisation_id)
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereHas('organisation', function ($query): void {
                $query->where('approve_status', true);
            })
            ->with([
                'venue:id,name',
                'images' => fn ($query) => $query
                    ->where('type', 'banner')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Published news selected on a microsite home (via site_categories).
     *
     * @param  array{site_unique_id: string, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function homeCategoryNews(array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $newsIds = $this->categoryTargetIds($site->id, SiteCategoryTypeEnum::NEWS);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = News::query()
            ->whereIn('id', $newsIds)
            ->where('organisation_id', $site->organisation_id)
            ->where('site_id', $site->id)
            ->where('status', NewsStatusEnum::PUBLISHED)
            ->with('linkedEvent:id,unique_id,name,status')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Active support contacts for a published microsite.
     *
     * @param  array{site_unique_id: string, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function supportContacts(array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = SiteSupportContact::query()
            ->where('site_id', $site->id)
            ->where('status', StatusEnum::ACTIVE)
            ->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    private function resolveActiveSite(string $siteUniqueId): Site
    {
        $uniqueId = trim($siteUniqueId);

        $site = Site::query()
            ->where('unique_id', $uniqueId)
            ->where('status', SiteStatusEnum::ACTIVE)
            ->first();

        if ($site === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_not_found'),
            );
        }

        return $site;
    }

    /**
     * @return list<int>
     */
    private function categoryTargetIds(int $siteId, SiteCategoryTypeEnum $type): array
    {
        return SiteCategory::query()
            ->where('site_id', $siteId)
            ->where('category_type', $type)
            ->orderBy('id')
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
