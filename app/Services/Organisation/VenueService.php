<?php

namespace App\Services\Organisation;

use App\Models\Facility;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuitableForOption;
use App\Models\VenueType;
use App\Services\Service;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VenueService extends Service
{
    /**
     * Create a venue for the authenticated organiser's organisation.
     *
     * @param  array<string, mixed>  $data
     * @return array{venue: Venue}
     */
    public function create(User $user, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);

        $venue = Venue::query()->create([
            'organisation_id' => $organisation->id,
            'unique_id' => 'VEN'.strtoupper(substr((string) Str::ulid(), 0, 8)),
            'name' => $data['name'],
            'venue_type_id' => $data['venue_type_id'] ?? null,
            'description' => $data['description'] ?? null,
            'maximum_capacity' => $data['maximum_capacity'] ?? null,
            'standing_capacity' => $data['standing_capacity'] ?? null,
            'seated_capacity' => $data['seated_capacity'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_private_hire_available' => $data['is_private_hire_available'] ?? false,
            'private_hire_description' => $data['private_hire_description'] ?? null,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return [
            'venue' => $venue->fresh(['venueType']),
        ];
    }

    /**
     * System facilities plus optional organisation-specific facilities.
     *
     * @return array{facilities: Collection<int, Facility>}
     */
    public function getFacilitiesListForVenue(?int $organisationId = null): array
    {
        $facilities = Facility::query()
            ->where('is_active', true)
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id');

                if ($organisationId !== null) {
                    $query->orWhere('organisation_id', $organisationId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'facilities' => $facilities,
        ];
    }

    /**
     * System suitable-for options plus optional organisation-specific options.
     *
     * @return array{options: Collection<int, VenueSuitableForOption>}
     */
    public function getVenueSuitableForOptions(?int $organisationId = null): array
    {
        $options = VenueSuitableForOption::query()
            ->where('is_active', true)
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id');

                if ($organisationId !== null) {
                    $query->orWhere('organisation_id', $organisationId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'options' => $options,
        ];
    }

    /**
     * Active venue types.
     *
     * @return array{venue_types: Collection<int, VenueType>}
     */
    public function getVenueTypeList(): array
    {
        $venueTypes = VenueType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return [
            'venue_types' => $venueTypes,
        ];
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
