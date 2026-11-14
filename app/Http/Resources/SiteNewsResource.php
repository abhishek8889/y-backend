<?php

namespace App\Http\Resources;

use App\Enum\NewsStatusEnum;
use App\Models\News;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class SiteNewsResource extends JsonResource
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
            // Convenience for FE: when scheduled, this is the same as published_at.
            'scheduled_to' => $this->status === NewsStatusEnum::SCHEDULED
                ? $this->published_at?->toIso8601String()
                : null,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'linked_event' => $this->when(
                $this->relationLoaded('linkedEvent'),
                fn () => $this->linkedEvent === null
                    ? null
                    : [
                        'id' => $this->linkedEvent->id,
                        'unique_id' => $this->linkedEvent->unique_id,
                        'name' => $this->linkedEvent->name,
                        'status' => $this->linkedEvent->status?->value ?? $this->linkedEvent->status,
                    ],
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
