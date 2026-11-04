<?php

namespace App\Services\Organisation;

use App\Enum\StatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Site;
use App\Models\SiteSupportContact;
use App\Models\User;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SiteSupportContactService extends Service
{
    public function __construct(
        private readonly OrganisationService $organisation,
    ) {}

    /**
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
        $siteId = isset($filters['site_id']) && $filters['site_id'] !== '' && $filters['site_id'] !== null
            ? (int) $filters['site_id']
            : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        if (filled($status) && StatusEnum::tryFrom($status) === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('validation.in', ['attribute' => 'status']),
            );
        }

        if ($siteId !== null) {
            $this->assertSiteBelongsToOrganisation($organisationId, $siteId);
        }

        $paginator = SiteSupportContact::query()
            ->whereHas('site', fn ($query) => $query->where('organisation_id', $organisationId))
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
                        $inner->where('department_name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
                },
            )
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * @param  array{
     *     site_id: int,
     *     department_name: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     status?: string|null
     * }  $data
     * @return array{contact: SiteSupportContact}
     */
    public function create(User $actor, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $siteId = $this->assertSiteBelongsToOrganisation($organisationId, (int) $data['site_id']);

        $status = StatusEnum::tryFrom((string) ($data['status'] ?? StatusEnum::ACTIVE->value))
            ?? StatusEnum::ACTIVE;

        try {
            $contact = DB::transaction(function () use ($actor, $siteId, $data, $status): SiteSupportContact {
                return SiteSupportContact::query()->create([
                    'site_id' => $siteId,
                    'department_name' => (string) $data['department_name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'status' => $status,
                    'created_by' => $actor->id,
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_support_contact_create_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'contact' => $contact,
        ];
    }

    /**
     * @param  array{
     *     site_id?: int,
     *     department_name?: string,
     *     email?: string|null,
     *     phone?: string|null,
     *     status?: string
     * }  $data
     * @return array{contact: SiteSupportContact}
     */
    public function update(User $actor, int $contactId, array $data): array
    {
        $organisationId = $this->organisationId($actor);
        $contact = $this->resolveContact($organisationId, $contactId);

        $updates = [];

        if (array_key_exists('site_id', $data)) {
            $updates['site_id'] = $this->assertSiteBelongsToOrganisation(
                $organisationId,
                (int) $data['site_id'],
            );
        }

        if (array_key_exists('department_name', $data)) {
            $updates['department_name'] = (string) $data['department_name'];
        }

        if (array_key_exists('email', $data)) {
            $updates['email'] = $data['email'];
        }

        if (array_key_exists('phone', $data)) {
            $updates['phone'] = $data['phone'];
        }

        if (array_key_exists('status', $data)) {
            $updates['status'] = StatusEnum::from((string) $data['status']);
        }

        try {
            if ($updates !== []) {
                $contact->fill($updates);
                $contact->save();
            }
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_support_contact_update_failed'),
                $exception->getMessage(),
            );
        }

        return [
            'contact' => $contact->fresh() ?? $contact,
        ];
    }

    /**
     * @return array{deleted: bool}
     */
    public function delete(User $actor, int $contactId): array
    {
        $organisationId = $this->organisationId($actor);
        $contact = $this->resolveContact($organisationId, $contactId);

        try {
            $contact->delete();
        } catch (Throwable $exception) {
            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('messages.site_support_contact_delete_failed'),
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

    private function resolveContact(int $organisationId, int $contactId): SiteSupportContact
    {
        $contact = SiteSupportContact::query()
            ->whereKey($contactId)
            ->whereHas('site', fn ($query) => $query->where('organisation_id', $organisationId))
            ->first();

        if ($contact === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.site_support_contact_not_found'),
            );
        }

        return $contact;
    }
}
