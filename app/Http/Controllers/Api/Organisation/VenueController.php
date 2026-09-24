<?php

namespace App\Http\Controllers\Api\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organisation\StoreVenueRequest;
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
