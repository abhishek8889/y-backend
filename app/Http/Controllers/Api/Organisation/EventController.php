<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\ManageEventStatusRequest;
use App\Http\Requests\Organisation\StoreEventRequest;
use App\Http\Requests\Organisation\StoreEventTicketOfferRequest;
use App\Http\Requests\Organisation\StoreEventTicketRequest;
use App\Http\Requests\Organisation\UpdateEventRequest;
use App\Http\Requests\Organisation\UpdateEventTicketOfferRequest;
use App\Http\Requests\Organisation\UpdateEventTicketRequest;
use App\Http\Resources\EventCategoryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventTicketOfferResource;
use App\Http\Resources\EventTicketResource;
use App\Services\Organisation\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EventController extends Controller
{
    /**
     * Create an event for the authenticated organiser's organisation.
     */
    public function create(StoreEventRequest $request, EventService $event): JsonResponse
    {
        try {
            $response = $event->create($request->user(), $request->validated());

            return $this->success(
                __('messages.event_created'),
                EventResource::make($response['event']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update an event for the authenticated organiser's organisation.
     */
    public function update(
        UpdateEventRequest $request,
        EventService $event,
        int $event_id,
    ): JsonResponse {
        try {
            $response = $event->update(
                $request->user(),
                $event_id,
                $request->validated(),
            );

            return $this->success(
                __('messages.event_updated'),
                EventResource::make($response['event']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create a ticket for an organisation event.
     */
    public function createTicket(StoreEventTicketRequest $request, EventService $event): JsonResponse
    {
        try {
            $response = $event->createTicket($request->user(), $request->validated());

            return $this->success(
                __('messages.event_ticket_created'),
                EventTicketResource::make($response['ticket']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List tickets for an event belonging to the authenticated organiser's organisation.
     */
    public function listTickets(Request $request, EventService $event, int $event_id): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
            ];

            $response = $event->listTickets($request->user(), $event_id, $filters);

            return $this->success(
                __('messages.event_ticket_list'),
                EventTicketResource::collection($response['tickets']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update a ticket for an organisation event.
     */
    public function updateTicket(
        UpdateEventTicketRequest $request,
        EventService $event,
        int $ticket_id,
    ): JsonResponse {
        try {
            $response = $event->updateTicket(
                $request->user(),
                $ticket_id,
                $request->validated(),
            );

            return $this->success(
                __('messages.event_ticket_updated'),
                EventTicketResource::make($response['ticket']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete a ticket and all of its offers.
     */
    public function deleteTicket(Request $request, EventService $event, int $ticket_id): JsonResponse
    {
        try {
            $response = $event->deleteTicket($request->user(), $ticket_id);

            return $this->success(
                __('messages.event_ticket_deleted'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Create an offer for an organisation event ticket.
     */
    public function createTicketOffer(StoreEventTicketOfferRequest $request, EventService $event): JsonResponse
    {
        try {
            $response = $event->createTicketOffer($request->user(), $request->validated());

            return $this->success(
                __('messages.event_ticket_offer_created'),
                EventTicketOfferResource::make($response['offer']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List offers for a ticket belonging to the authenticated organiser's organisation.
     */
    public function listTicketOffers(Request $request, EventService $event, int $ticket_id): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
            ];

            $response = $event->listTicketOffers($request->user(), $ticket_id, $filters);

            return $this->success(
                __('messages.event_ticket_offer_list'),
                EventTicketOfferResource::collection($response['offers']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update an offer for an organisation event ticket.
     */
    public function updateTicketOffer(
        UpdateEventTicketOfferRequest $request,
        EventService $event,
        int $offer_id,
    ): JsonResponse {
        try {
            $response = $event->updateTicketOffer(
                $request->user(),
                $offer_id,
                $request->validated(),
            );

            return $this->success(
                __('messages.event_ticket_offer_updated'),
                EventTicketOfferResource::make($response['offer']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete an offer for an organisation event ticket.
     */
    public function deleteTicketOffer(Request $request, EventService $event, int $offer_id): JsonResponse
    {
        try {
            $response = $event->deleteTicketOffer($request->user(), $offer_id);

            return $this->success(
                __('messages.event_ticket_offer_deleted'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List events for the authenticated organiser's organisation.
     */
    public function list(Request $request, EventService $event): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'event_category_id' => $request->filled('event_category_id')
                    ? $request->integer('event_category_id')
                    : null,
                'search' => $request->filled('search')
                    ? $request->string('search')->toString()
                    : null,
                'per_page' => $request->filled('per_page')
                    ? $request->integer('per_page')
                    : null,
                'cursor' => $request->filled('cursor')
                    ? $request->string('cursor')->toString()
                    : null,
            ];

            $response = $event->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.event_list'),
                EventResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete an event for the authenticated organiser's organisation.
     */
    public function delete(Request $request, EventService $event, int $event_id): JsonResponse
    {
        try {
            $response = $event->delete($request->user(), $event_id);

            return $this->success(
                __('messages.event_deleted'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Manage event status transitions for the authenticated organiser's organisation.
     */
    public function manageStatus(
        ManageEventStatusRequest $request,
        EventService $event,
        int $event_id,
    ): JsonResponse {
        try {
            $response = $event->manageStatus(
                $request->user(),
                $event_id,
                $request->validated('status'),
            );

            return $this->success(
                __('messages.event_status_updated'),
                EventResource::make($response['event']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List system event categories and optional organisation-specific categories.
     */
    public function getEventCategoryList(Request $request, EventService $event): JsonResponse
    {
        try {
            $organisationId = $request->filled('organisation_id')
                ? $request->integer('organisation_id')
                : null;

            $response = $event->getEventCategoryList($organisationId);

            return $this->success(
                __('messages.event_categories_list'),
                EventCategoryResource::collection($response['categories']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
