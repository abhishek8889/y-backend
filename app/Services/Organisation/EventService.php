<?php

namespace App\Services\Organisation;

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Exceptions\ServiceException;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Services\MediaService;
use App\Services\Service;
use App\Support\UniqueIdGenerator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EventService extends Service
{
    public function __construct(
        private MediaService $media,
    ) {}

    /**
     * Create an event for the authenticated organiser's organisation.
     * New events are always saved as draft until tickets exist.
     *
     * @param  array<string, mixed>  $data
     * @return array{event: Event}
     */
    public function create(User $user, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);

        /** @var list<array{type: string, path: string, alt_text?: string|null, sort_order?: int|null}> $images */
        $images = array_values($data['images'] ?? []);

        if (isset($data['venue_id'])) {
            $this->assertVenueBelongsToOrganisation((int) $data['venue_id'], $organisation->id);
        }

        if (isset($data['event_category_id'])) {
            $this->assertCategoryAvailable((int) $data['event_category_id'], $organisation->id);
        }

        $this->assertOwnedImagePaths($user, $images);

        try {
            /** @var Event $event */
            $event = DB::transaction(function () use ($user, $organisation, $data, $images): Event {
                $uniqueId = UniqueIdGenerator::generate('EVT');
                $attempts = 0;

                while (
                    Event::query()->where('unique_id', $uniqueId)->exists()
                    && $attempts < 25
                ) {
                    $uniqueId = UniqueIdGenerator::generate('EVT');
                    $attempts++;
                }

                $event = Event::query()->create([
                    'organisation_id' => $organisation->id,
                    'unique_id' => $uniqueId,
                    'name' => $data['name'],
                    'event_category_id' => $data['event_category_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'venue_id' => $data['venue_id'] ?? null,
                    'capacity' => $data['capacity'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'status' => EventStatusEnum::DRAFT,
                    'published_at' => null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->createImages($user, $event, $images);

                return $event;
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'event' => $event->fresh([
                'category',
                'venue',
                'images',
            ]),
        ];
    }

    /**
     * Update an event for the authenticated organiser's organisation.
     * Status is managed separately via manage-status.
     *
     * @param  array<string, mixed>  $data
     * @return array{event: Event}
     */
    public function update(User $user, int $eventId, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $event = $this->resolveOrganisationEvent($organisation->id, $eventId);

        if (in_array($event->status, [EventStatusEnum::CANCELLED, EventStatusEnum::COMPLETED], true)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_update_final_status'),
            );
        }

        /** @var list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>|null $images */
        $images = array_key_exists('images', $data)
            ? array_values($data['images'] ?? [])
            : null;

        if (array_key_exists('venue_id', $data) && $data['venue_id'] !== null) {
            $this->assertVenueBelongsToOrganisation((int) $data['venue_id'], $organisation->id);
        }

        if (array_key_exists('event_category_id', $data) && $data['event_category_id'] !== null) {
            $this->assertCategoryAvailable((int) $data['event_category_id'], $organisation->id);
        }

        if ($images !== null) {
            $this->assertUpdateImagePayload($user, $event, $images);
        }

        try {
            DB::transaction(function () use ($user, $event, $data, $images): void {
                $event->update([
                    'name' => $data['name'],
                    'event_category_id' => $data['event_category_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'venue_id' => $data['venue_id'] ?? null,
                    'capacity' => $data['capacity'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'updated_by' => $user->id,
                ]);

                if ($images !== null) {
                    $this->syncImages($user, $event, $images);
                }
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'event' => $event->fresh([
                'category',
                'venue',
                'images',
            ]),
        ];
    }

    /**
     * List events for the authenticated organiser's organisation.
     *
     * @param  array{status?: string|null, event_category_id?: int|null, search?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $user, array $filters = []): array
    {
        $organisation = $this->resolveOrganisation($user);

        $status = $filters['status'] ?? null;
        $eventCategoryId = $filters['event_category_id'] ?? null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Event::query()
            ->where('organisation_id', $organisation->id)
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
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
            ->with(['category', 'venue', 'images'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Delete an event and all related tickets, offers, and images.
     *
     * @return array{event_id: int}
     */
    public function delete(User $user, int $eventId): array
    {
        $organisation = $this->resolveOrganisation($user);
        $event = $this->resolveOrganisationEvent($organisation->id, $eventId);

        if ($event->status !== EventStatusEnum::DRAFT) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_delete_non_draft'),
            );
        }

        $imagePaths = $event->images()->pluck('path')->all();

        try {
            DB::transaction(function () use ($event): void {
                $ticketIds = $event->tickets()->pluck('id');

                if ($ticketIds->isNotEmpty()) {
                    EventTicketOffer::query()
                        ->whereIn('event_ticket_id', $ticketIds)
                        ->delete();

                    EventTicket::query()
                        ->whereIn('id', $ticketIds)
                        ->delete();
                }

                $event->images()->delete();
                $event->delete();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        foreach ($imagePaths as $path) {
            if (is_string($path) && $path !== '') {
                $this->media->deleteStoredFile($path);
            }
        }

        return [
            'event_id' => $eventId,
        ];
    }

    /**
     * Create a ticket for an organisation event.
     *
     * @param  array<string, mixed>  $data
     * @return array{ticket: EventTicket}
     */
    public function createTicket(User $user, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $event = $this->resolveOrganisationEvent($organisation->id, (int) $data['event_id']);

        $this->assertEventAllowsTicketChanges($event);

        try {
            /** @var EventTicket $ticket */
            $ticket = DB::transaction(function () use ($user, $event, $data): EventTicket {
                return EventTicket::query()->create([
                    'event_id' => $event->id,
                    'unique_id' => $this->generateUniqueId('TKT', EventTicket::class),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'base_price' => $data['base_price'],
                    'currency' => strtoupper((string) ($data['currency'] ?? 'USD')),
                    'quantity_cap' => $data['quantity_cap'],
                    'badge_label' => $data['badge_label'] ?? null,
                    'entitlement' => $data['entitlement'] ?? null,
                    'status' => EventTicketStatusEnum::DRAFT,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'ticket' => $ticket,
        ];
    }

    /**
     * List tickets for an event belonging to the authenticated organiser's organisation.
     *
     * @param  array{status?: string|null}  $filters
     * @return array{tickets: Collection<int, EventTicket>, event: Event}
     */
    public function listTickets(User $user, int $eventId, array $filters = []): array
    {
        $organisation = $this->resolveOrganisation($user);
        $event = $this->resolveOrganisationEvent($organisation->id, $eventId);
        $status = $filters['status'] ?? null;

        $tickets = EventTicket::query()
            ->where('event_id', $event->id)
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->with('offers')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'event' => $event,
            'tickets' => $tickets,
        ];
    }

    /**
     * Update a ticket for an organisation event.
     *
     * @param  array<string, mixed>  $data
     * @return array{ticket: EventTicket}
     */
    public function updateTicket(User $user, int $ticketId, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $ticket = $this->resolveOrganisationTicket($organisation->id, $ticketId);
        $event = $ticket->event;

        $this->assertEventAllowsTicketChanges($event);

        $quantityCap = (int) $data['quantity_cap'];
        $offerQuantityTotal = (int) $ticket->offers()->sum('quantity_cap');

        if ($offerQuantityTotal > 0 && $quantityCap < $offerQuantityTotal) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_ticket_quantity_below_offers'),
            );
        }

        try {
            DB::transaction(function () use ($user, $ticket, $data, $quantityCap): void {
                $ticket->update([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'base_price' => $data['base_price'],
                    'currency' => strtoupper((string) ($data['currency'] ?? $ticket->currency)),
                    'quantity_cap' => $quantityCap,
                    'badge_label' => $data['badge_label'] ?? null,
                    'entitlement' => $data['entitlement'] ?? null,
                    'status' => isset($data['status'])
                        ? EventTicketStatusEnum::from($data['status'])
                        : $ticket->status,
                    'sort_order' => $data['sort_order'] ?? $ticket->sort_order,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'ticket' => $ticket->fresh(['offers']),
        ];
    }

    /**
     * Delete a ticket and all of its offers.
     *
     * @return array{ticket_id: int}
     */
    public function deleteTicket(User $user, int $ticketId): array
    {
        $organisation = $this->resolveOrganisation($user);
        $ticket = $this->resolveOrganisationTicket($organisation->id, $ticketId);
        $event = $ticket->event;

        $this->assertEventAllowsTicketChanges($event);

        try {
            DB::transaction(function () use ($ticket): void {
                $ticket->offers()->delete();
                $ticket->delete();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'ticket_id' => $ticketId,
        ];
    }

    /**
     * Create an offer for an organisation event ticket.
     *
     * @param  array<string, mixed>  $data
     * @return array{offer: EventTicketOffer}
     */
    public function createTicketOffer(User $user, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $ticket = $this->resolveOrganisationTicket($organisation->id, (int) $data['ticket_id']);
        $event = $ticket->event;

        $this->assertEventAllowsTicketChanges($event);

        $this->assertOfferQuantityWithinTicketCap(
            $ticket,
            (int) $data['quantity_cap'],
        );

        try {
            /** @var EventTicketOffer $offer */
            $offer = DB::transaction(function () use ($user, $ticket, $data): EventTicketOffer {
                return EventTicketOffer::query()->create([
                    'event_ticket_id' => $ticket->id,
                    'unique_id' => $this->generateUniqueId('OFR', EventTicketOffer::class),
                    'name' => $data['name'],
                    'price' => $data['price'],
                    'quantity_cap' => $data['quantity_cap'],
                    'sale_starts_at' => $data['sale_starts_at'] ?? null,
                    'sale_ends_at' => $data['sale_ends_at'] ?? null,
                    'max_per_order' => $data['max_per_order'] ?? null,
                    'access' => $data['access'] ?? 'public',
                    'status' => EventTicketOfferStatusEnum::DRAFT,
                    'sort_order' => $data['sort_order'] ?? 0,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'offer' => $offer,
        ];
    }

    /**
     * List offers for a ticket belonging to the authenticated organiser's organisation.
     *
     * @param  array{status?: string|null}  $filters
     * @return array{offers: Collection<int, EventTicketOffer>, ticket: EventTicket}
     */
    public function listTicketOffers(User $user, int $ticketId, array $filters = []): array
    {
        $organisation = $this->resolveOrganisation($user);
        $ticket = $this->resolveOrganisationTicket($organisation->id, $ticketId);
        $status = $filters['status'] ?? null;

        $offers = EventTicketOffer::query()
            ->where('event_ticket_id', $ticket->id)
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'ticket' => $ticket,
            'offers' => $offers,
        ];
    }

    /**
     * Update an offer for an organisation event ticket.
     *
     * @param  array<string, mixed>  $data
     * @return array{offer: EventTicketOffer}
     */
    public function updateTicketOffer(User $user, int $offerId, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $offer = $this->resolveOrganisationTicketOffer($organisation->id, $offerId);
        $ticket = $offer->ticket;
        $event = $ticket->event;

        $this->assertEventAllowsTicketChanges($event);

        $this->assertOfferQuantityWithinTicketCap(
            $ticket,
            (int) $data['quantity_cap'],
            $offer->id,
        );

        try {
            DB::transaction(function () use ($user, $offer, $data): void {
                $offer->update([
                    'name' => $data['name'],
                    'price' => $data['price'],
                    'quantity_cap' => $data['quantity_cap'],
                    'sale_starts_at' => $data['sale_starts_at'] ?? null,
                    'sale_ends_at' => $data['sale_ends_at'] ?? null,
                    'max_per_order' => $data['max_per_order'] ?? null,
                    'access' => $data['access'] ?? $offer->access,
                    'status' => isset($data['status'])
                        ? EventTicketOfferStatusEnum::from($data['status'])
                        : $offer->status,
                    'sort_order' => $data['sort_order'] ?? $offer->sort_order,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'offer' => $offer->fresh(),
        ];
    }

    /**
     * Delete an offer for an organisation event ticket.
     *
     * @return array{offer_id: int}
     */
    public function deleteTicketOffer(User $user, int $offerId): array
    {
        $organisation = $this->resolveOrganisation($user);
        $offer = $this->resolveOrganisationTicketOffer($organisation->id, $offerId);
        $event = $offer->ticket->event;

        $this->assertEventAllowsTicketChanges($event);

        try {
            DB::transaction(function () use ($offer): void {
                $offer->delete();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'offer_id' => $offerId,
        ];
    }

    /**
     * Transition an event status with business rules.
     *
     * @return array{event: Event}
     */
    public function manageStatus(User $user, int $eventId, string $status): array
    {
        $organisation = $this->resolveOrganisation($user);
        $event = $this->resolveOrganisationEvent($organisation->id, $eventId);
        $target = EventStatusEnum::from($status);

        if ($event->status === $target) {
            return [
                'event' => $event->fresh([
                    'category',
                    'venue',
                    'images',
                ]),
            ];
        }

        match ($target) {
            EventStatusEnum::PUBLISHED => $this->assertCanPublish($event),
            EventStatusEnum::CANCELLED => $this->assertCanCancel($event),
            EventStatusEnum::COMPLETED => $this->assertCanComplete($event),
            EventStatusEnum::DRAFT => $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_revert_to_draft'),
            ),
        };

        try {
            DB::transaction(function () use ($user, $event, $target): void {
                $event->status = $target;
                $event->published_at = $target === EventStatusEnum::PUBLISHED
                    ? ($event->published_at ?? Carbon::now())
                    : $event->published_at;
                $event->updated_by = $user->id;
                $event->save();
            });
        } catch (Throwable $exception) {
            if ($exception instanceof ServiceException) {
                throw $exception;
            }

            report($exception);

            $this->fail(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                __('exceptions.server_error'),
            );
        }

        return [
            'event' => $event->fresh([
                'category',
                'venue',
                'images',
            ]),
        ];
    }

    /**
     * System event categories plus optional organisation-specific categories.
     *
     * @return array{categories: Collection<int, EventCategory>}
     */
    public function getEventCategoryList(?int $organisationId = null): array
    {
        $categories = EventCategory::query()
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id');

                if ($organisationId !== null) {
                    $query->orWhere('organisation_id', $organisationId);
                }
            })
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories,
        ];
    }

    private function assertCanPublish(Event $event): void
    {
        if ($event->status !== EventStatusEnum::DRAFT) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_invalid_status_transition'),
            );
        }

        if ($event->starts_at === null || $event->ends_at === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_publish_without_schedule'),
            );
        }

        if ($event->ends_at->lte(Carbon::now())) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_publish_past_end'),
            );
        }

        $hasTicketWithOffer = $event->tickets()
            ->whereHas('offers')
            ->exists();

        if (! $hasTicketWithOffer) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_publish_without_ticket'),
            );
        }
    }

    private function assertCanCancel(Event $event): void
    {
        if (! in_array($event->status, [EventStatusEnum::DRAFT, EventStatusEnum::PUBLISHED], true)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_invalid_status_transition'),
            );
        }

        if ($event->starts_at !== null && Carbon::now()->gte($event->starts_at)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_cancel_after_start'),
            );
        }
    }

    private function assertCanComplete(Event $event): void
    {
        if ($event->status !== EventStatusEnum::PUBLISHED) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_invalid_status_transition'),
            );
        }

        if ($event->ends_at === null) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_complete_without_end'),
            );
        }

        if (Carbon::now()->lte($event->ends_at)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_cannot_complete_before_end'),
            );
        }
    }

    private function assertEventAllowsTicketChanges(Event $event): void
    {
        if (in_array($event->status, [EventStatusEnum::CANCELLED, EventStatusEnum::COMPLETED], true)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_ticket_event_not_editable'),
            );
        }
    }

    /**
     * Ensure offer quantities for a ticket never exceed the ticket quantity_cap in total.
     */
    private function assertOfferQuantityWithinTicketCap(
        EventTicket $ticket,
        int $incomingQuantityCap,
        ?int $excludeOfferId = null,
    ): void {
        $existingTotal = (int) $ticket->offers()
            ->when(
                $excludeOfferId !== null,
                fn ($query) => $query->whereKeyNot($excludeOfferId),
            )
            ->sum('quantity_cap');

        if (($existingTotal + $incomingQuantityCap) > $ticket->quantity_cap) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_ticket_offer_quantity_exceeds_ticket'),
            );
        }
    }

    /**
     * @param  class-string<EventTicket|EventTicketOffer>  $model
     */
    private function generateUniqueId(string $prefix, string $model): string
    {
        $uniqueId = UniqueIdGenerator::generate($prefix);
        $attempts = 0;

        while (
            $model::query()->where('unique_id', $uniqueId)->exists()
            && $attempts < 25
        ) {
            $uniqueId = UniqueIdGenerator::generate($prefix);
            $attempts++;
        }

        return $uniqueId;
    }

    private function resolveOrganisationTicket(int $organisationId, int $ticketId): EventTicket
    {
        $ticket = EventTicket::query()
            ->whereKey($ticketId)
            ->whereHas(
                'event',
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->with('event')
            ->first();

        if ($ticket === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_ticket_not_found'),
            );
        }

        return $ticket;
    }

    private function resolveOrganisationTicketOffer(int $organisationId, int $offerId): EventTicketOffer
    {
        $offer = EventTicketOffer::query()
            ->whereKey($offerId)
            ->whereHas(
                'ticket.event',
                fn ($query) => $query->where('organisation_id', $organisationId),
            )
            ->with('ticket.event')
            ->first();

        if ($offer === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_ticket_offer_not_found'),
            );
        }

        return $offer;
    }

    private function resolveOrganisationEvent(int $organisationId, int $eventId): Event
    {
        $event = Event::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($eventId)
            ->first();

        if ($event === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.event_not_found'),
            );
        }

        return $event;
    }

    /**
     * @param  list<array{type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function createImages(User $user, Event $event, array $images): void
    {
        if ($images === []) {
            return;
        }

        $destinationDirectory = 'events/'.$event->id;

        foreach ($images as $index => $image) {
            $moved = $this->media->moveOwnedTmpTo($user, $image['path'], $destinationDirectory);

            $event->images()->create([
                'type' => $image['type'],
                'path' => $moved['path'],
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => $image['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Sync event images: keep/update by id, add new tmp paths, delete omitted ones.
     *
     * @param  list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function syncImages(User $user, Event $event, array $images): void
    {
        $destinationDirectory = 'events/'.$event->id;
        $keptIds = [];

        foreach ($images as $index => $image) {
            $path = ltrim($image['path'], '/');
            $imageId = isset($image['id']) ? (int) $image['id'] : null;
            $sortOrder = $image['sort_order'] ?? $index;

            if ($imageId !== null) {
                $existing = $event->images()->whereKey($imageId)->first();

                if ($existing === null) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.event_invalid_image'),
                    );
                }

                if ($this->media->isTmpPath($path)) {
                    $moved = $this->media->moveOwnedTmpTo($user, $path, $destinationDirectory);
                    $oldPath = $existing->path;
                    $existing->update([
                        'type' => $image['type'],
                        'path' => $moved['path'],
                        'alt_text' => $image['alt_text'] ?? null,
                        'sort_order' => $sortOrder,
                    ]);

                    if ($oldPath !== $moved['path']) {
                        $this->media->deleteStoredFile($oldPath);
                    }
                } else {
                    if ($path !== $existing->path) {
                        $this->fail(
                            Response::HTTP_UNPROCESSABLE_ENTITY,
                            __('messages.event_invalid_image_path'),
                        );
                    }

                    $existing->update([
                        'type' => $image['type'],
                        'alt_text' => $image['alt_text'] ?? null,
                        'sort_order' => $sortOrder,
                    ]);
                }

                $keptIds[] = $existing->id;

                continue;
            }

            $moved = $this->media->moveOwnedTmpTo($user, $path, $destinationDirectory);

            $created = $event->images()->create([
                'type' => $image['type'],
                'path' => $moved['path'],
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $keptIds[] = $created->id;
        }

        $removed = $event->images()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn ($query) => $query)
            ->get();

        foreach ($removed as $image) {
            $this->media->deleteStoredFile($image->path);
            $image->delete();
        }
    }

    private function assertVenueBelongsToOrganisation(int $venueId, int $organisationId): void
    {
        $exists = Venue::query()
            ->whereKey($venueId)
            ->where('organisation_id', $organisationId)
            ->exists();

        if (! $exists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_invalid_venue'),
            );
        }
    }

    private function assertCategoryAvailable(int $categoryId, int $organisationId): void
    {
        $exists = EventCategory::query()
            ->whereKey($categoryId)
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id')
                    ->orWhere('organisation_id', $organisationId);
            })
            ->exists();

        if (! $exists) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.event_invalid_category'),
            );
        }
    }

    /**
     * @param  list<array{type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function assertOwnedImagePaths(User $user, array $images): void
    {
        $paths = [];

        foreach ($images as $image) {
            $path = ltrim($image['path'], '/');

            if (in_array($path, $paths, true)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.event_duplicate_image_path'),
                );
            }

            $paths[] = $path;
            $this->media->assertOwnedTmpFile($user, $path);
        }
    }

    /**
     * @param  list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function assertUpdateImagePayload(User $user, Event $event, array $images): void
    {
        $paths = [];

        foreach ($images as $image) {
            $path = ltrim($image['path'], '/');

            if (in_array($path, $paths, true)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.event_duplicate_image_path'),
                );
            }

            $paths[] = $path;

            if ($this->media->isTmpPath($path)) {
                $this->media->assertOwnedTmpFile($user, $path);

                continue;
            }

            $imageId = isset($image['id']) ? (int) $image['id'] : null;

            if ($imageId === null) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.event_invalid_image_path'),
                );
            }

            $belongs = $event->images()
                ->whereKey($imageId)
                ->where('path', $path)
                ->exists();

            if (! $belongs) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.event_invalid_image_path'),
                );
            }
        }
    }

    private function resolveOrganisation(User $user): Organisation
    {
        $organisationId = $user->loginContext()['organisation_id'];

        $organisation = $organisationId !== null
            ? Organisation::query()->find($organisationId)
            : null;

        if ($organisation === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.organisation_not_found'),
            );
        }

        return $organisation;
    }
}
