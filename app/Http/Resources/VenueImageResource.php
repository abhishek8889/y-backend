<?php

namespace App\Http\Resources;

use App\Models\VenueImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
        $disk = (string) config('media.local_disk', 'public');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'path' => $this->path,
            'url' => Storage::disk($disk)->url($this->path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
        ];
    }
}
