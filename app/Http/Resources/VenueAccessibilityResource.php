<?php

namespace App\Http\Resources;

use App\Models\VenueAccessibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VenueAccessibility
 */
class VenueAccessibilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'accessible_entrance' => $this->accessible_entrance,
            'accessible_toilet' => $this->accessible_toilet,
            'wheelchair_access' => $this->wheelchair_access,
            'notes' => $this->notes,
        ];
    }
}
