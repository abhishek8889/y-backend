<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\SiteCategoryTypeEnum;
use App\Enum\SiteStatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\News;
use App\Models\OurTeamMember;
use App\Models\Site;
use App\Models\SiteCategory;
use App\Models\SiteSupportContact;
use App\Models\Venue;
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
            ->publiclyVisible()
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

    /**
     * All team members for a published microsite.
     *
     * @param  array{site_unique_id: string, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function ourTeamMembers(array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = OurTeamMember::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Public news detail by slug for an active microsite.
     *
     * @param  array{site_unique_id: string}  $filters
     * @return array{news: News}
     */
    public function newsDetail(string $slug, array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $slug = trim($slug);

        $news = News::query()
            ->where('site_id', $site->id)
            ->where('organisation_id', $site->organisation_id)
            ->where('slug', $slug)
            ->publiclyVisible()
            ->with([
                'linkedEvent.category:id,organisation_id,name,slug',
                'linkedEvent.venue.venueType',
                'linkedEvent.venue.address',
                'linkedEvent.images' => fn ($query) => $query
                    ->where('type', 'banner')
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->first();

        if ($news === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_news_not_found'),
            );
        }

        return [
            'news' => $news,
        ];
    }

    /**
     * Public venue detail by unique_id.
     *
     * - With site_unique_id: scoped to that active microsite's organisation
     * - Without site_unique_id: any active venue belonging to an approved organisation
     *
     * @param  array{site_unique_id?: string|null}  $filters
     * @return array{venue: Venue}
     */
    public function venueDetail(string $uniqueId, array $filters = []): array
    {
        $uniqueId = trim($uniqueId);
        $siteUniqueId = isset($filters['site_unique_id'])
            ? trim((string) $filters['site_unique_id'])
            : '';

        $organisationId = null;
        if ($siteUniqueId !== '') {
            $organisationId = $this->resolveActiveSite($siteUniqueId)->organisation_id;
        }

        $venue = Venue::query()
            ->where('unique_id', $uniqueId)
            ->where('status', StatusEnum::ACTIVE->value)
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->when(
                $organisationId === null,
                fn ($query) => $query->whereHas(
                    'organisation',
                    fn ($org) => $org->where('approve_status', true),
                ),
            )
            ->with([
                'venueType',
                'address',
                'contacts',
                'accessibility',
                'logistics',
                'facilities',
                'suitableForOptions',
                'images',
            ])
            ->first();

        if ($venue === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.venue_not_found'),
            );
        }

        return [
            'venue' => $venue,
        ];
    }

    /**
     * Public team member detail by member_unique_id for an active microsite.
     *
     * @param  array{site_unique_id: string}  $filters
     * @return array{member: OurTeamMember}
     */
    public function ourTeamMemberDetail(string $memberUniqueId, array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $memberUniqueId = trim($memberUniqueId);

        $member = OurTeamMember::query()
            ->where('site_id', $site->id)
            ->where('member_unique_id', $memberUniqueId)
            ->with('user:id,first_name,last_name,email')
            ->first();

        if ($member === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.our_team_member_not_found'),
            );
        }

        return [
            'member' => $member,
        ];
    }

    /**
     * All published news for a microsite (not limited to home category selections).
     *
     * @param  array{
     *     site_unique_id: string,
     *     search?: string|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function allNews(array $filters): array
    {
        $site = $this->resolveActiveSite((string) $filters['site_unique_id']);
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = News::query()
            ->where('site_id', $site->id)
            ->where('organisation_id', $site->organisation_id)
            ->publiclyVisible()
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where(function ($inner) use ($like): void {
                        $inner->where('headline', 'like', $like)
                            ->orWhere('excerpt', 'like', $like)
                            ->orWhere('description', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('author', 'like', $like)
                            ->orWhere('content_role', 'like', $like);
                    });
                },
            )
            ->with('linkedEvent:id,unique_id,name,status')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
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
