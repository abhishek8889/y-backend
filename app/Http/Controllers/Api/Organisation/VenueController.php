<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreVenueRequest;
use App\Http\Requests\Organisation\UpdateVenueRequest;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\VenueResource;
use App\Http\Resources\VenueSuitableForOptionResource;
use App\Http\Resources\VenueTypeResource;
use App\Services\Organisation\VenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class VenueController extends Controller
{
    /**
     * Create a venue for the authenticated organiser's organisation.
     */
    public function create(StoreVenueRequest $request, VenueService $venue): JsonResponse
    {
        try {
            $response = $venue->create($request->user(), $request->validated());

            return $this->success(
                __('messages.venue_created'),
                VenueResource::make($response['venue']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Update a venue for the authenticated organiser's organisation.
     */
    public function update(UpdateVenueRequest $request, VenueService $venue, int $venue_id): JsonResponse
    {
        try {
            $response = $venue->update($request->user(), $venue_id, $request->validated());

            return $this->success(
                __('messages.venue_updated'),
                VenueResource::make($response['venue']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Delete a venue for the authenticated organiser's organisation.
     */
    public function delete(Request $request, VenueService $venue, int $venue_id): JsonResponse
    {
        try {
            $response = $venue->delete($request->user(), $venue_id);

            return $this->success(
                __('messages.venue_deleted'),
                $response,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List venues for the authenticated organiser's organisation.
     */
    public function list(Request $request, VenueService $venue): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->filled('status')
                    ? $request->string('status')->toString()
                    : null,
                'venue_type_id' => $request->filled('venue_type_id')
                    ? $request->integer('venue_type_id')
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

            $response = $venue->list($request->user(), $filters);
            $paginator = $response['paginator'];

            return $this->successPaginated(
                __('messages.venue_list'),
                VenueResource::collection($paginator->items()),
                $paginator,
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * Get venue details for the authenticated organiser's organisation.
     */
    public function details(Request $request, VenueService $venue, int $venue_id): JsonResponse
    {
        try {
            $response = $venue->details($request->user(), $venue_id);

            return $this->success(
                __('messages.venue_details'),
                VenueResource::make($response['venue']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List system facilities and optional organisation-specific facilities.
     */
    public function getFacilitiesListForVenue(Request $request, VenueService $venue): JsonResponse
    {
        try {
            $organisationId = $request->filled('organisation_id')
                ? $request->integer('organisation_id')
                : null;

            $response = $venue->getFacilitiesListForVenue($organisationId);

            return $this->success(
                __('messages.venue_facilities_list'),
                FacilityResource::collection($response['facilities']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List system suitable-for options and optional organisation-specific options.
     */
    public function getVenueSuitableForOptions(Request $request, VenueService $venue): JsonResponse
    {
        try {
            $organisationId = $request->filled('organisation_id')
                ? $request->integer('organisation_id')
                : null;

            $response = $venue->getVenueSuitableForOptions($organisationId);

            return $this->success(
                __('messages.venue_suitable_for_options_list'),
                VenueSuitableForOptionResource::collection($response['options']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }

    /**
     * List active venue types.
     */
    public function getVenueTypeList(VenueService $venue): JsonResponse
    {
        try {
            $response = $venue->getVenueTypeList();

            return $this->success(
                __('messages.venue_types_list'),
                VenueTypeResource::collection($response['venue_types']),
            );
        } catch (Throwable $exception) {
            return $this->failed($exception);
        }
    }
}
