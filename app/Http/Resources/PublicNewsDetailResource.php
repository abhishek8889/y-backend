<?php

namespace App\Http\Resources;

use App\Enum\NewsStatusEnum;
use App\Models\News;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full public news detail, including linked event basics + banner images.
 *
 * @mixin News
 */
class PublicNewsDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'site_id' => $this->site_id,
            'featured_image' => $this->mediaAsset($this->featured_image),
            'author' => $this->author,
            'content_role' => $this->content_role,
            'headline' => $this->headline,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'description' => $this->description,
            'linked_event_id' => $this->linked_event_id,
            'status' => $this->status?->value ?? $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_to' => $this->status === NewsStatusEnum::SCHEDULED
                ? $this->published_at?->toIso8601String()
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'linked_event' => $this->when(
                $this->relationLoaded('linkedEvent'),
                function () {
                    $event = $this->linkedEvent;
                    if ($event === null) {
                        return null;
                    }

                    return [
                        'id' => $event->id,
                        'unique_id' => $event->unique_id,
                        'name' => $event->name,
                        'description' => $event->description,
                        'starts_at' => $event->starts_at?->toIso8601String(),
                        'ends_at' => $event->ends_at?->toIso8601String(),
                        'timezone' => $event->timezone,
                        'status' => $event->status?->value ?? $event->status,
                        'category' => $event->relationLoaded('category') && $event->category !== null
                            ? EventCategoryResource::make($event->category)
                            : null,
                        'venue' => $event->relationLoaded('venue') && $event->venue !== null
                            ? [
                                'id' => $event->venue->id,
                                'unique_id' => $event->venue->unique_id,
                                'name' => $event->venue->name,
                                'description' => $event->venue->description,
                                'venue_type' => $event->venue->relationLoaded('venueType')
                                    && $event->venue->venueType !== null
                                    ? VenueTypeResource::make($event->venue->venueType)
                                    : null,
                                'address' => $event->venue->relationLoaded('address')
                                    && $event->venue->address !== null
                                    ? VenueAddressResource::make($event->venue->address)
                                    : null,
                            ]
                            : null,
                        'images' => $event->relationLoaded('images')
                            ? EventImageResource::collection(
                                $event->images->where('type', 'banner')->values(),
                            )
                            : [],
                    ];
                },
            ),
        ];
    }

    /**
     * @return array{path: string, url: string}|null
     */
    private function mediaAsset(mixed $path): ?array
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $trimmed = trim($path);

        return [
            'path' => $trimmed,
            'url' => app(MediaService::class)->url($trimmed),
        ];
    }
}
