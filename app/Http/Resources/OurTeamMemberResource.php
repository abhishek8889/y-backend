<?php

namespace App\Http\Resources;

use App\Models\OurTeamMember;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OurTeamMember
 */
class OurTeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_unique_id' => $this->member_unique_id,
            'site_id' => $this->site_id,
            'user_id' => $this->user_id,
            'image_url' => $this->mediaAsset($this->image_url),
            'name' => $this->name,
            'designation' => $this->designation,
            'about' => $this->about,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->when(
                $this->relationLoaded('user'),
                fn () => $this->user === null
                    ? null
                    : [
                        'id' => $this->user->id,
                        'first_name' => $this->user->first_name ?? null,
                        'last_name' => $this->user->last_name ?? null,
                        'email' => $this->user->email ?? null,
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
