<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal site row for microsite pickers (id, name, hostname).
 *
 * @mixin Site
 */
class MicrositeListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hostname' => $this->relationLoaded('domain')
                ? $this->domain?->hostname
                : null,
        ];
    }
}
