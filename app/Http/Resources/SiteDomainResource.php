<?php

namespace App\Http\Resources;

use App\Models\SiteDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteDomain
 */
class SiteDomainResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'hostname' => $this->hostname,
            'status' => $this->status?->value ?? $this->status,
            'vercel_added' => (bool) $this->vercel_added,
            'dns_instructions' => $this->dns_instructions,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
