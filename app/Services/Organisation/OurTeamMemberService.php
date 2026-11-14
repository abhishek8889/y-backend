<?php

namespace App\Services\Organisation;

use App\Exceptions\ServiceException;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\OurTeamMember;
use App\Models\Site;
use App\Models\User;
use App\Services\MediaService;
use App\Services\Service;
use App\Support\UniqueIdGenerator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OurTeamMemberService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
        private readonly MediaService $media,
    ) {}

    /**
     * @param  array{
     *     site_id?: int|string|null,
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
        $siteId = isset($filters['site_id']) && $filters['site_id'] !== '' && $filters['site_id'] !== null
            ? (int) $filters['site_id']
            : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if ($siteId !== null) {
            $this->assertSiteBelongsToOrganisation($organisationId, $siteId);
        }

        $paginator = OurTeamMember::query()
            ->whereHas('site', fn ($query) => $query->where('organisation_id', $organisationId))
            ->when(
                $siteId !== null,
                fn ($query) => $query->where('site_id', $siteId),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where(function ($inner) use ($like): void {
                        $inner->where('name', 'like', $like)
                            ->orWhere('designation', 'like', $like)
                            ->orWhere('about', 'like', $like);
                    });
                },
            )
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * @param  array{
     *     site_id: int,
     *     user_id?: int|null,
     *     image_url?: mixed,
     *     name: string,
     *     designation?: string|null,
     *     about?: string|null,
     *     joined_at?: string|null
     * }  $data
     * @return array{member: OurTeamMember}
     */
    public function create(User $actor, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $siteId = $this->assertSiteBelongsToOrganisation($organisationId, (int) $data['site_id']);

        try {
            $member = DB::transaction(function () use ($actor, $siteId, $data): OurTeamMember {
                $member = OurTeamMember::query()->create([
                    'member_unique_id' => $this->generateMemberUniqueId(),
                    'site_id' => $siteId,
                    'user_id' => $data['user_id'] ?? null,
                    'image_url' => null,
                    'name' => (string) $data['name'],
                    'designation' => $data['designation'] ?? null,
                    'about' => $data['about'] ?? null,
                    'joined_at' => $data['joined_at'] ?? null,
                ]);

                if (array_key_exists('image_url', $data)) {
                    $member->image_url = $this->resolveImagePath(
                        $actor,
                        $siteId,
                        $member->id,
                        $data['image_url'] ?? null,
                        null,
                    );
                    $member->save();
                }

                return $member->load('user:id,first_name,last_name,email');
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.our_team_member_create_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'member' => $member,
        ];
    }

    /**
     * @param  array{
     *     site_id?: int,
     *     user_id?: int|null,
     *     image_url?: mixed,
     *     name?: string,
     *     designation?: string|null,
     *     about?: string|null,
     *     joined_at?: string|null
     * }  $data
     * @return array{member: OurTeamMember}
     */
    public function update(User $actor, int $memberId, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $member = $this->resolveMember($organisationId, $memberId);

        $updates = [];

        if (array_key_exists('site_id', $data)) {
            $updates['site_id'] = $this->assertSiteBelongsToOrganisation(
                $organisationId,
                (int) $data['site_id'],
            );
        }

        if (array_key_exists('user_id', $data)) {
            $updates['user_id'] = $data['user_id'];
        }

        if (array_key_exists('name', $data)) {
            $updates['name'] = (string) $data['name'];
        }

        if (array_key_exists('designation', $data)) {
            $updates['designation'] = $data['designation'];
        }

        if (array_key_exists('about', $data)) {
            $updates['about'] = $data['about'];
        }

        if (array_key_exists('joined_at', $data)) {
            $updates['joined_at'] = $data['joined_at'];
        }

        try {
            DB::transaction(function () use ($actor, $member, $data, $updates): void {
                $siteId = (int) ($updates['site_id'] ?? $member->site_id);

                if (array_key_exists('image_url', $data)) {
                    $updates['image_url'] = $this->resolveImagePath(
                        $actor,
                        $siteId,
                        $member->id,
                        $data['image_url'] ?? null,
                        $member->image_url,
                    );
                }

                if ($updates !== []) {
                    $member->fill($updates);
                    $member->save();
                }
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.our_team_member_update_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'member' => ($member->fresh() ?? $member)->load('user:id,first_name,last_name,email'),
        ];
    }

    /**
     * @return array{deleted: bool}
     */
    public function delete(User $actor, int $memberId): array
    {
        $organisationId = $this->organisationId($actor);
        $member = $this->resolveMember($organisationId, $memberId);

        try {
            $imagePath = $member->image_url;
            $member->delete();

            if (is_string($imagePath) && $imagePath !== '') {
                $this->media->deleteStoredFile($imagePath);
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.our_team_member_delete_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'deleted' => true,
        ];
    }

    private function generateMemberUniqueId(): string
    {
        $uniqueId = UniqueIdGenerator::generate('OTM');

        while (OurTeamMember::query()->where('member_unique_id', $uniqueId)->exists()) {
            $uniqueId = UniqueIdGenerator::generate('OTM');
        }

        return $uniqueId;
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

    private function resolveMember(int $organisationId, int $memberId): OurTeamMember
    {
        $member = OurTeamMember::query()
            ->whereKey($memberId)
            ->whereHas('site', fn ($query) => $query->where('organisation_id', $organisationId))
            ->first();

        if ($member === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.our_team_member_not_found'),
            );
        }

        return $member;
    }

    private function resolveImagePath(
        User $actor,
        int $siteId,
        int $memberId,
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
                'sites/'.$siteId.'/team/'.$memberId,
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
