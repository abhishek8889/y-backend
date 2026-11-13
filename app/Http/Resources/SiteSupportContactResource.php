<?php

namespace App\Http\Resources;

use App\Models\SiteSupportContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteSupportContact
 */
class SiteSupportContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'department_name' => $this->department_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status?->value ?? $this->status,
            'show_in_footer' => (bool) $this->show_in_footer,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
