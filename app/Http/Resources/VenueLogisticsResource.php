<?php

namespace App\Http\Resources;

use App\Models\VenueLogistics;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VenueLogistics
 */
class VenueLogisticsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parking_information' => $this->parking_information,
            'public_transport_information' => $this->public_transport_information,
            'travel_information' => $this->travel_information,
        ];
    }
}
