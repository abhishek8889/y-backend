<?php

namespace App\Services\Organisation;

use App\Enum\NewsStatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Event;
use App\Models\News;
use App\Models\Site;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteNewsService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly MediaService $media,
    ) {}

    /**
     * Cursor-paginated news for the organiser's organisation.
     *
     * @param  array{
     *     site_id?: int|string|null,
     *     status?: string|null,
     *     search?: string|null,
     *     per_page?: int|string|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $actor, array $filters = []): array
    {
        $organisationId = $this->organisationId($actor);
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $status = isset($filters['status']) ? trim((string) $filters['status']) : null;
        $siteId = isset($filters['site_id']) && $filters['site_id'] !== ''
            ? (int) $filters['site_id']
            : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if (filled($status) && NewsStatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('validation.in', ['attribute' => 'status']),
            );
        }

        if ($siteId !== null) {
            $this->resolveSiteId($organisationId, $siteId);
        }

        $paginator = News::query()
            ->where('organisation_id', $organisationId)
            ->when(
                $siteId !== null,
                fn ($query) => $query->where('site_id', $siteId),
            )
            ->when(
                filled($status),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where(function ($inner) use ($like): void {
                        $inner->where('headline', 'like', $like)
                            ->orWhere('slug', 'like', $like)
                            ->orWhere('excerpt', 'like', $like);
                    });
                },
            )
            ->with(['linkedEvent:id,unique_id,name,status'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Create a news item for the organiser's organisation.
     *
     * @param  array{
     *     site_id?: int|null,
     *     featured_image?: string|null,
     *     headline: string,
     *     slug?: string|null,
     *     excerpt?: string|null,
     *     description?: string|null,
     *     linked_event_id?: int|null,
     *     status?: string|null,
     *     published_at?: string|null
     * }  $data
     * @return array{news: News}
     */
    public function create(User $actor, array $data): array
    {
        $organisationId = $this->organisationId($actor);

        $siteId = $this->resolveSiteId($organisationId, $data['site_id'] ?? null);
        $linkedEventId = $this->resolveLinkedEventId($organisationId, $data['linked_event_id'] ?? null);

        $status = NewsStatusEnum::tryFrom((string) ($data['status'] ?? NewsStatusEnum::DRAFT->value))
            ?? NewsStatusEnum::DRAFT;

        $publishedAt = $this->resolvePublishedAt($status, $data['published_at'] ?? null);

        $slug = $this->generateUniqueSlug(
            filled($data['slug'] ?? null) ? (string) $data['slug'] : (string) $data['headline'],
            $organisationId,
        );

        try {
            $news = DB::transaction(function () use (
                $actor,
                $organisationId,
                $siteId,
                $data,
                $slug,
                $linkedEventId,
                $status,
                $publishedAt,
            ): News {
                $news = News::query()->create([
                    'organisation_id' => $organisationId,
                    'site_id' => $siteId,
                    'featured_image' => null,
                    'headline' => (string) $data['headline'],
                    'slug' => $slug,
                    'excerpt' => $data['excerpt'] ?? null,
                    'description' => $data['description'] ?? null,
                    'linked_event_id' => $linkedEventId,
                    'status' => $status,
                    'published_at' => $publishedAt,
                    'created_by' => $actor->id,
                ]);

                if (array_key_exists('featured_image', $data)) {
                    $news->featured_image = $this->resolveFeaturedImagePath(
                        $actor,
                        $news,
                        $data['featured_image'] ?? null,
                        null,
                    );
                    $news->save();
                }

                return $news->load('linkedEvent');
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_news_create_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'news' => $news,
        ];
    }

    /**
     * Update news content fields (not status — use manageStatus).
     *
     * @param  array{
     *     site_id?: int|null,
     *     featured_image?: string|null,
     *     headline?: string,
     *     slug?: string|null,
     *     excerpt?: string|null,
     *     description?: string|null,
     *     linked_event_id?: int|null
     * }  $data
     * @return array{news: News}
     */
    public function update(User $actor, int $newsId, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $news = $this->resolveNews($organisationId, $newsId);

        $updates = [];

        if (array_key_exists('site_id', $data)) {
            $updates['site_id'] = $this->resolveSiteId($organisationId, $data['site_id']);
        }

        if (array_key_exists('featured_image', $data)) {
            $updates['featured_image'] = $this->resolveFeaturedImagePath(
                $actor,
                $news,
                $data['featured_image'] ?? null,
                $news->featured_image,
            );
        }

        if (array_key_exists('headline', $data)) {
            $updates['headline'] = (string) $data['headline'];
        }

        if (array_key_exists('slug', $data)) {
            $slugSource = filled($data['slug'] ?? null)
                ? (string) $data['slug']
                : (string) ($updates['headline'] ?? $news->headline);
            $updates['slug'] = $this->generateUniqueSlug($slugSource, $organisationId, $news->id);
        } elseif (array_key_exists('headline', $data) && ! array_key_exists('slug', $data)) {
            // keep existing slug when only headline changes
        }

        if (array_key_exists('excerpt', $data)) {
            $updates['excerpt'] = $data['excerpt'];
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }

        if (array_key_exists('linked_event_id', $data)) {
            $updates['linked_event_id'] = $this->resolveLinkedEventId(
                $organisationId,
                $data['linked_event_id'],
            );
        }

        try {
            if ($updates !== []) {
                $news->fill($updates);
                $news->save();
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_news_update_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'news' => $news->fresh(['linkedEvent']) ?? $news->load('linkedEvent'),
        ];
    }

    /**
     * Switch news between draft and published.
     *
     * @param  array{status: string, published_at?: string|null}  $data
     * @return array{news: News}
     */
    public function manageStatus(User $actor, int $newsId, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $news = $this->resolveNews($organisationId, $newsId);

        $status = NewsStatusEnum::from((string) $data['status']);
        $publishedAt = $this->resolvePublishedAt(
            $status,
            $data['published_at'] ?? null,
            $news->published_at,
        );

        if ($status === NewsStatusEnum::DRAFT) {
            $publishedAt = null;
        }

        $news->fill([
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
        $news->save();

        return [
            'news' => $news->fresh(['linkedEvent']) ?? $news->load('linkedEvent'),
        ];
    }

    /**
     * Delete a news item belonging to the organisation.
     *
     * @return array{deleted: bool}
     */
    public function delete(User $actor, int $newsId): array
    {
        $organisationId = $this->organisationId($actor);
        $news = $this->resolveNews($organisationId, $newsId);

        try {
            $featuredImage = $news->featured_image;
            $news->delete();
            if (is_string($featuredImage) && $featuredImage !== '') {
                $this->media->deleteStoredFile($featuredImage);
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_news_delete_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'deleted' => true,
        ];
    }

    private function organisationId(User $actor): int
    {
        return (int) $this->organisation->getOrganisationDetail($actor)['organisation']->id;
    }

    private function resolveNews(int $organisationId, int $newsId): News
    {
        $news = News::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($newsId)
            ->first();

        if ($news === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_news_not_found'),
            );
        }

        return $news;
    }

    private function resolveSiteId(int $organisationId, mixed $siteId): ?int
    {
        if ($siteId === null) {
            return null;
        }

        $id = (int) $siteId;
        $siteExists = Site::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($id)
            ->exists();

        if (! $siteExists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_not_found'),
            );
        }

        return $id;
    }

    private function resolveLinkedEventId(int $organisationId, mixed $linkedEventId): ?int
    {
        if ($linkedEventId === null) {
            return null;
        }

        $id = (int) $linkedEventId;
        $eventBelongsToOrg = Event::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($id)
            ->exists();

        if (! $eventBelongsToOrg) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.site_news_linked_event_invalid'),
            );
        }

        return $id;
    }

    private function resolvePublishedAt(
        NewsStatusEnum $status,
        mixed $publishedAt,
        mixed $existingPublishedAt = null,
    ): mixed {
        if ($status !== NewsStatusEnum::PUBLISHED) {
            return filled($publishedAt) ? $publishedAt : null;
        }

        if (filled($publishedAt)) {
            return $publishedAt;
        }

        if ($existingPublishedAt !== null) {
            return $existingPublishedAt;
        }

        return now();
    }

    private function generateUniqueSlug(string $value, int $organisationId, ?int $ignoreNewsId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'news';
        }

        $slug = $base;
        $suffix = 2;

        while (
            News::query()
                ->where('organisation_id', $organisationId)
                ->where('slug', $slug)
                ->when($ignoreNewsId !== null, fn ($query) => $query->where('id', '!=', $ignoreNewsId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Persist a media path for news featured images. Moves owned tmp uploads into news/{id}/…
     */
    private function resolveFeaturedImagePath(
        User $actor,
        News $news,
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
                'news/'.$news->id.'/featured',
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
