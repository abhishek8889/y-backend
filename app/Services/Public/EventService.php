<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Organisation;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

class EventService extends Service
{
    /**
     * Public published events list (marketplace or organisation microsite).
     *
     * @param  array{
     *     organisation_id?: int|null,
     *     organisation_unique_id?: string|null,
     *     event_category_id?: int|null,
     *     search?: string|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(array $filters = []): array
    {
        $organisationId = $this->resolveOrganisationId($filters);
        $eventCategoryId = $filters['event_category_id'] ?? null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Event::query()
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereHas('organisation', function ($query): void {
                $query->where('approve_status', true);
            })
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->when(
                $eventCategoryId !== null,
                fn ($query) => $query->where('event_category_id', $eventCategoryId),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%')
                            ->orWhere('unique_id', 'like', '%'.$search.'%');
                    });
                },
            )
            ->with([
                'venue:id,name',
                'images' => fn ($query) => $query
                    ->where('type', 'banner')
                    ->orderBy('sort_order'),
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Public published event details by unique_id.
     *
     * @return array{event: Event}
     */
    public function details(string $uniqueId, ?int $organisationId = null): array
    {
        $event = Event::query()
            ->where('unique_id', $uniqueId)
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereHas('organisation', function ($query): void {
                $query->where('approve_status', true);
            })
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->with([
                'category',
                'venue.address',
                'images',
                'tickets',
                'organisation:id,unique_id,name,logo,banner,description',
            ])
            ->first();

        if ($event === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_not_found'),
            );
        }

        return [
            'event' => $event,
        ];
    }

    /**
     * Tickets and nested offers for a published public event.
     *
     * @return array{tickets: Collection<int, EventTicket>}
     */
    public function tickets(string $uniqueId, ?int $organisationId = null): array
    {
        $event = Event::query()
            ->where('unique_id', $uniqueId)
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereHas('organisation', function ($query): void {
                $query->where('approve_status', true);
            })
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->first();

        if ($event === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_not_found'),
            );
        }

        $tickets = $event->tickets()
            ->whereIn('status', [
                EventTicketStatusEnum::ACTIVE,
                EventTicketStatusEnum::SOLD_OUT,
            ])
            ->with([
                'offers' => function ($query): void {
                    $now = now();

                    $query
                        ->whereIn('status', [
                            EventTicketOfferStatusEnum::ACTIVE,
                            EventTicketOfferStatusEnum::SOLD_OUT,
                        ])
                        ->where(function ($inner) use ($now): void {
                            $inner->whereNull('sale_starts_at')
                                ->orWhere('sale_starts_at', '<=', $now);
                        })
                        ->where(function ($inner) use ($now): void {
                            $inner->whereNull('sale_ends_at')
                                ->orWhere('sale_ends_at', '>=', $now);
                        })
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'tickets' => $tickets,
        ];
    }

    /**
     * @param  array{organisation_id?: int|null, organisation_unique_id?: string|null}  $filters
     */
    private function resolveOrganisationId(array $filters): ?int
    {
        if (isset($filters['organisation_id']) && filled($filters['organisation_id'])) {
            return (int) $filters['organisation_id'];
        }

        $uniqueId = isset($filters['organisation_unique_id'])
            ? trim((string) $filters['organisation_unique_id'])
            : null;

        if (! filled($uniqueId)) {
            return null;
        }

        $organisation = Organisation::query()
            ->where('unique_id', $uniqueId)
            ->where('approve_status', true)
            ->first(['id']);

        if ($organisation === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_not_found'),
            );
        }

        return (int) $organisation->id;
    }
}
