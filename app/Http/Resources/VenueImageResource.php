<?php

namespace App\Http\Resources;

use App\Models\VenueImage;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VenueImage
 */
class VenueImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->path,
            'url' => app(MediaService::class)->url((string) $this->path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
        ];
    }
}
