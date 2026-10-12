<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\StatusEnum;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Event;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\Service;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Symfony\Component\HttpFoundation\Response;

class WishlistService extends Service
{
    /**
     * Cursor-paginated wishlist for the authenticated user.
     *
     * @param  array{
     *     organisation_id?: int|null,
     *     per_page?: int|null,
     *     cursor?: string|null
     * }  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $user, array $filters = []): array
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $organisationId = $filters['organisation_id'] ?? null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Wishlist::query()
            ->where('user_id', $user->id)
            ->when(
                $organisationId !== null,
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->with([
                'event' => fn ($query) => $query->with([
                    'venue:id,name',
                    'images' => fn ($images) => $images
                        ->where('type', 'banner')
                        ->orderBy('sort_order'),
                ]),
            ])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Save an event to the authenticated user's wishlist.
     *
     * @param  array{event_id: int, organisation_id?: int|null}  $data
     * @return array{wishlist: Wishlist}
     */
    public function store(User $user, array $data): array
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $event = Event::query()
            ->whereKey($data['event_id'])
            ->where('status', EventStatusEnum::PUBLISHED)
            ->whereHas('organisation', function ($query): void {
                $query->where('approve_status', true);
            })
            ->when(
                isset($data['organisation_id']) && filled($data['organisation_id']),
                fn ($query) => $query->where('organisation_id', (int) $data['organisation_id']),
            )
            ->first();

        if ($event === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_not_found'),
            );
        }

        $existing = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->first();

        if ($existing !== null) {
            $this->fail(
                Response::HTTP_CONFLICT,
                __('messages.wishlist_already_exists'),
            );
        }

        $wishlist = Wishlist::query()->create([
            'user_id' => $user->id,
            'organisation_id' => $data['organisation_id'] ?? $event->organisation_id,
            'event_id' => $event->id,
        ]);

        return [
            'wishlist' => $wishlist,
        ];
    }

    /**
     * Remove an event from the authenticated user's wishlist.
     *
     * @param  array{event_id: int}  $data
     * @return array{event_id: int}
     */
    public function remove(User $user, array $data): array
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $eventId = (int) $data['event_id'];

        $wishlist = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->first();

        if ($wishlist === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.wishlist_not_found'),
            );
        }

        $wishlist->delete();

        return [
            'event_id' => $eventId,
        ];
    }
}
