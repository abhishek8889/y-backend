<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventListResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventTicketResource;
use App\Services\Public\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class EventController extends Controller
{
    /**
     * Public published events list (marketplace or organisation-scoped).
     */
    public function list(Request $request, EventService $event): JsonResponse
    {
        try {
            $filters = [
                'organisation_id' => $request->filled('organisation_id')
                    ? $request->integer('organisation_id')
                    : null,
                'organisation_unique_id' => $request->filled('organisation_unique_id')
                    ? $request->string('organisation_unique_id')->toString()
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

            $response = $event->list($filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.public_event_list'),
                EventListResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Public published event details by unique_id.
     */
    public function details(
        Request $request,
        EventService $event,
        string $unique_id,
    ): JsonResponse {
        try {
            $organisationId = $request->filled('organisation_id')
                ? $request->integer('organisation_id')
                : null;

            $response = $event->details($unique_id, $organisationId);

            return $this->success(
                __('messages.public_event_details'),
                EventResource::make($response['event']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Tickets and offers for a published public event.
     */
    public function tickets(
        Request $request,
        EventService $event,
        string $unique_id,
    ): JsonResponse {
        try {
            $organisationId = $request->filled('organisation_id')
                ? $request->integer('organisation_id')
                : null;

            $response = $event->tickets($unique_id, $organisationId);

            return $this->success(
                __('messages.public_event_tickets'),
                EventTicketResource::collection($response['tickets']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
