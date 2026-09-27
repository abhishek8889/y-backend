<?php

namespace App\Services\Organisation;

use App\Exceptions\ServiceException;
use App\Http\Responses\CursorPaginatedResponse;
use App\Models\Facility;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSuitableForOption;
use App\Models\VenueType;
use App\Services\MediaService;
use App\Services\Service;
use App\Support\UniqueIdGenerator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VenueService extends Service
{
    public function __construct(
        private MediaService $media,
    ) {}

    /**
     * Create a venue for the authenticated organiser's organisation.
     *
     * @param  array<string, mixed>  $data
     * @return array{venue: Venue}
     */
    public function create(User $user, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);

        $facilityIds = array_values(array_unique(array_map(
            'intval',
            $data['facility_ids'] ?? [],
        )));

        $suitableForOptionIds = array_values(array_unique(array_map(
            'intval',
            $data['suitable_for_option_ids'] ?? [],
        )));
        /** @var list<array{type: string, path: string, alt_text?: string|null, sort_order?: int|null}> $images */
        $images = array_values($data['images'] ?? []);

        $this->assertFacilitiesBelongToOrganisation($facilityIds, $organisation->id);
        $this->assertSuitableForOptionsBelongToOrganisation($suitableForOptionIds, $organisation->id);
        $this->assertOwnedImagePaths($user, $images);

        try {
            /** @var Venue $venue */
            $venue = DB::transaction(function () use (
                $user,
                $organisation,
                $data,
                $facilityIds,
                $suitableForOptionIds,
                $images,
            ): Venue {
                $venue = Venue::query()->create([
                    'organisation_id' => $organisation->id,
                    'unique_id' => UniqueIdGenerator::generate('VEN'),
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

                $this->createAddress($venue, $data['address'] ?? null);
                $this->createContact($venue, $data['contact'] ?? null);
                $this->createAccessibility($venue, $data['accessibility'] ?? null);
                $this->createLogistics($venue, $data['logistics'] ?? null);

                if ($facilityIds !== []) {
                    $venue->facilities()->sync($facilityIds);
                }

                if ($suitableForOptionIds !== []) {
                    $venue->suitableForOptions()->sync($suitableForOptionIds);
                }

                $this->createImages($user, $venue, $images);

                return $venue;
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
            'venue' => $venue->fresh([
                'venueType',
                'address',
                'contacts',
                'accessibility',
                'logistics',
                'facilities',
                'suitableForOptions',
                'images',
            ]),
        ];
    }

    /**
     * List venues for the authenticated organiser's organisation.
     *
     * @param  array{status?: string|null, venue_type_id?: int|null, search?: string|null, per_page?: int|null, cursor?: string|null}  $filters
     * @return array{paginator: CursorPaginator}
     */
    public function list(User $user, array $filters = []): array
    {
        $organisation = $this->resolveOrganisation($user);

        $status = $filters['status'] ?? null;
        $venueTypeId = $filters['venue_type_id'] ?? null;
        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        $perPage = CursorPaginatedResponse::resolvePerPage($filters['per_page'] ?? null);

        $paginator = Venue::query()
            ->where('organisation_id', $organisation->id)
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $venueTypeId !== null,
                fn ($query) => $query->where('venue_type_id', $venueTypeId),
            )
            ->when(
                filled($search),
                function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner->where('name', 'like', '%'.$search.'%')
                            ->orWhere('description', 'like', '%'.$search.'%');
                    });
                },
            )
            ->with(['venueType', 'address', 'images'])
            ->orderByDesc('id')
            ->cursorPaginate($perPage, ['*'], 'cursor', $filters['cursor'] ?? null);

        return [
            'paginator' => $paginator,
        ];
    }

    /**
     * Update a venue belonging to the authenticated organiser's organisation.
     *
     * @param  array<string, mixed>  $data
     * @return array{venue: Venue}
     */
    public function update(User $user, int $venueId, array $data): array
    {
        $organisation = $this->resolveOrganisation($user);
        $venue = $this->resolveOrganisationVenue($organisation->id, $venueId);

        $facilityIds = array_key_exists('facility_ids', $data)
            ? array_values(array_unique(array_map('intval', $data['facility_ids'] ?? [])))
            : null;
        $suitableForOptionIds = array_key_exists('suitable_for_option_ids', $data)
            ? array_values(array_unique(array_map('intval', $data['suitable_for_option_ids'] ?? [])))
            : null;
        /** @var list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>|null $images */
        $images = array_key_exists('images', $data)
            ? array_values($data['images'] ?? [])
            : null;

        if ($facilityIds !== null) {
            $this->assertFacilitiesBelongToOrganisation($facilityIds, $organisation->id);
        }

        if ($suitableForOptionIds !== null) {
            $this->assertSuitableForOptionsBelongToOrganisation($suitableForOptionIds, $organisation->id);
        }

        if ($images !== null) {
            $this->assertUpdateImagePayload($user, $venue, $images);
        }

        try {
            DB::transaction(function () use (
                $user,
                $venue,
                $data,
                $facilityIds,
                $suitableForOptionIds,
                $images,
            ): void {
                $venue->update([
                    'name' => $data['name'],
                    'venue_type_id' => $data['venue_type_id'] ?? null,
                    'description' => $data['description'] ?? null,
                    'maximum_capacity' => $data['maximum_capacity'] ?? null,
                    'standing_capacity' => $data['standing_capacity'] ?? null,
                    'seated_capacity' => $data['seated_capacity'] ?? null,
                    'status' => $data['status'] ?? $venue->status,
                    'is_private_hire_available' => $data['is_private_hire_available'] ?? false,
                    'private_hire_description' => $data['private_hire_description'] ?? null,
                    'updated_by' => $user->id,
                ]);

                if (array_key_exists('address', $data)) {
                    $this->upsertAddress($venue, $data['address']);
                }

                if (array_key_exists('contact', $data)) {
                    $this->upsertContact($venue, $data['contact']);
                }

                if (array_key_exists('accessibility', $data)) {
                    $this->upsertAccessibility($venue, $data['accessibility']);
                }

                if (array_key_exists('logistics', $data)) {
                    $this->upsertLogistics($venue, $data['logistics']);
                }

                if ($facilityIds !== null) {
                    $venue->facilities()->sync($facilityIds);
                }

                if ($suitableForOptionIds !== null) {
                    $venue->suitableForOptions()->sync($suitableForOptionIds);
                }

                if ($images !== null) {
                    $this->syncImages($user, $venue, $images);
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
            'venue' => $venue->fresh([
                'venueType',
                'address',
                'contacts',
                'accessibility',
                'logistics',
                'facilities',
                'suitableForOptions',
                'images',
            ]),
        ];
    }

    /**
     * Delete a venue belonging to the authenticated organiser's organisation.
     *
     * @return array{venue_id: int}
     */
    public function delete(User $user, int $venueId): array
    {
        $organisation = $this->resolveOrganisation($user);
        $venue = $this->resolveOrganisationVenue($organisation->id, $venueId);

        $imagePaths = $venue->images()->pluck('path')->all();

        try {
            DB::transaction(function () use ($venue): void {
                $venue->facilities()->detach();
                $venue->suitableForOptions()->detach();
                $venue->delete();
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
            'venue_id' => $venueId,
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

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function createAddress(Venue $venue, ?array $address): void
    {
        if ($address === null) {
            return;
        }

        $venue->address()->create([
            'address_line_1' => $address['address_line_1'],
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'],
            'state' => $address['state'] ?? null,
            'postal_code' => $address['postal_code'],
            'country' => $address['country'],
            'latitude' => $address['latitude'] ?? null,
            'longitude' => $address['longitude'] ?? null,
            'google_maps_url' => $address['google_maps_url'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $contact
     */
    private function createContact(Venue $venue, ?array $contact): void
    {
        if ($contact === null) {
            return;
        }

        $venue->contacts()->create([
            'type' => $contact['type'] ?? 'general',
            'name' => $contact['name'],
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'alternative_phone' => $contact['alternative_phone'] ?? null,
            'website' => $contact['website'] ?? null,
            'facebook_url' => $contact['facebook_url'] ?? null,
            'instagram_url' => $contact['instagram_url'] ?? null,
            'twitter_url' => $contact['twitter_url'] ?? null,
            'youtube_url' => $contact['youtube_url'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $accessibility
     */
    private function createAccessibility(Venue $venue, ?array $accessibility): void
    {
        if ($accessibility === null) {
            return;
        }

        $venue->accessibility()->create([
            'accessible_entrance' => $accessibility['accessible_entrance'] ?? null,
            'accessible_toilet' => $accessibility['accessible_toilet'] ?? null,
            'wheelchair_access' => $accessibility['wheelchair_access'] ?? null,
            'notes' => $accessibility['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $logistics
     */
    private function createLogistics(Venue $venue, ?array $logistics): void
    {
        if ($logistics === null) {
            return;
        }

        $venue->logistics()->create([
            'parking_information' => $logistics['parking_information'] ?? null,
            'public_transport_information' => $logistics['public_transport_information'] ?? null,
            'travel_information' => $logistics['travel_information'] ?? null,
        ]);
    }

    /**
     * @param  list<array{type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function createImages(User $user, Venue $venue, array $images): void
    {
        if ($images === []) {
            return;
        }

        $destinationDirectory = 'venues/'.$venue->id;

        foreach ($images as $index => $image) {
            $moved = $this->media->moveOwnedTmpTo($user, $image['path'], $destinationDirectory);

            $venue->images()->create([
                'type' => $image['type'],
                'path' => $moved['path'],
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => $image['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Sync venue images: keep/update by id, add new tmp paths, delete omitted ones.
     *
     * @param  list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function syncImages(User $user, Venue $venue, array $images): void
    {
        $destinationDirectory = 'venues/'.$venue->id;
        $keptIds = [];

        foreach ($images as $index => $image) {
            $path = ltrim($image['path'], '/');
            $imageId = isset($image['id']) ? (int) $image['id'] : null;
            $sortOrder = $image['sort_order'] ?? $index;

            if ($imageId !== null) {
                $existing = $venue->images()->whereKey($imageId)->first();

                if ($existing === null) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.venue_invalid_image'),
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
                            __('messages.venue_invalid_image_path'),
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

            $created = $venue->images()->create([
                'type' => $image['type'],
                'path' => $moved['path'],
                'alt_text' => $image['alt_text'] ?? null,
                'sort_order' => $sortOrder,
            ]);

            $keptIds[] = $created->id;
        }

        $removed = $venue->images()
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn ($query) => $query)
            ->get();

        foreach ($removed as $image) {
            $this->media->deleteStoredFile($image->path);
            $image->delete();
        }
    }

    /**
     * @param  array<string, mixed>|null  $address
     */
    private function upsertAddress(Venue $venue, ?array $address): void
    {
        if ($address === null) {
            return;
        }

        $venue->address()->updateOrCreate(
            ['venue_id' => $venue->id],
            [
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'] ?? null,
                'city' => $address['city'],
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'],
                'country' => $address['country'],
                'latitude' => $address['latitude'] ?? null,
                'longitude' => $address['longitude'] ?? null,
                'google_maps_url' => $address['google_maps_url'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $contact
     */
    private function upsertContact(Venue $venue, ?array $contact): void
    {
        if ($contact === null) {
            return;
        }

        $type = $contact['type'] ?? 'general';

        $venue->contacts()->updateOrCreate(
            [
                'venue_id' => $venue->id,
                'type' => $type,
            ],
            [
                'name' => $contact['name'],
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'alternative_phone' => $contact['alternative_phone'] ?? null,
                'website' => $contact['website'] ?? null,
                'facebook_url' => $contact['facebook_url'] ?? null,
                'instagram_url' => $contact['instagram_url'] ?? null,
                'twitter_url' => $contact['twitter_url'] ?? null,
                'youtube_url' => $contact['youtube_url'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $accessibility
     */
    private function upsertAccessibility(Venue $venue, ?array $accessibility): void
    {
        if ($accessibility === null) {
            return;
        }

        $venue->accessibility()->updateOrCreate(
            ['venue_id' => $venue->id],
            [
                'accessible_entrance' => $accessibility['accessible_entrance'] ?? null,
                'accessible_toilet' => $accessibility['accessible_toilet'] ?? null,
                'wheelchair_access' => $accessibility['wheelchair_access'] ?? null,
                'notes' => $accessibility['notes'] ?? null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $logistics
     */
    private function upsertLogistics(Venue $venue, ?array $logistics): void
    {
        if ($logistics === null) {
            return;
        }

        $venue->logistics()->updateOrCreate(
            ['venue_id' => $venue->id],
            [
                'parking_information' => $logistics['parking_information'] ?? null,
                'public_transport_information' => $logistics['public_transport_information'] ?? null,
                'travel_information' => $logistics['travel_information'] ?? null,
            ],
        );
    }

    /**
     * @param  list<int>  $facilityIds
     */
    private function assertFacilitiesBelongToOrganisation(array $facilityIds, int $organisationId): void
    {
        if ($facilityIds === []) {
            return;
        }

        $validCount = Facility::query()
            ->whereIn('id', $facilityIds)
            ->where('is_active', true)
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id')
                    ->orWhere('organisation_id', $organisationId);
            })
            ->count();

        if ($validCount !== count($facilityIds)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.venue_invalid_facilities'),
            );
        }
    }

    /**
     * @param  list<int>  $optionIds
     */
    private function assertSuitableForOptionsBelongToOrganisation(array $optionIds, int $organisationId): void
    {
        if ($optionIds === []) {
            return;
        }

        $validCount = VenueSuitableForOption::query()
            ->whereIn('id', $optionIds)
            ->where('is_active', true)
            ->where(function ($query) use ($organisationId): void {
                $query->whereNull('organisation_id')
                    ->orWhere('organisation_id', $organisationId);
            })
            ->count();

        if ($validCount !== count($optionIds)) {
            $this->fail(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('messages.venue_invalid_suitable_for_options'),
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
                    __('messages.venue_duplicate_image_path'),
                );
            }

            $paths[] = $path;
            $this->media->assertOwnedTmpFile($user, $path);
        }
    }

    /**
     * @param  list<array{id?: int, type: string, path: string, alt_text?: string|null, sort_order?: int|null}>  $images
     */
    private function assertUpdateImagePayload(User $user, Venue $venue, array $images): void
    {
        $paths = [];

        foreach ($images as $image) {
            $path = ltrim($image['path'], '/');

            if (in_array($path, $paths, true)) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.venue_duplicate_image_path'),
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
                    __('messages.venue_invalid_image_path'),
                );
            }

            $belongs = $venue->images()
                ->whereKey($imageId)
                ->where('path', $path)
                ->exists();

            if (! $belongs) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.venue_invalid_image_path'),
                );
            }
        }
    }

    private function resolveOrganisationVenue(int $organisationId, int $venueId): Venue
    {
        $venue = Venue::query()
            ->where('organisation_id', $organisationId)
            ->whereKey($venueId)
            ->first();

        if ($venue === null) {
            $this->fail(
                Response::HTTP_NOT_FOUND,
                __('messages.venue_not_found'),
            );
        }

        return $venue;
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
