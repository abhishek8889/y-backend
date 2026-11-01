<?php

namespace App\Models;

use App\Enum\SiteDomainStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property string $hostname
 * @property SiteDomainStatusEnum $status
 * @property bool $vercel_added
 * @property array<string, mixed>|null $dns_instructions
 * @property Carbon|null $verified_at
 * @property Carbon|null $activated_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'site_id',
    'hostname',
    'status',
    'vercel_added',
    'dns_instructions',
    'verified_at',
    'activated_at',
    'last_error',
])]
class SiteDomain extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SiteDomainStatusEnum::class,
            'vercel_added' => 'boolean',
            'dns_instructions' => 'array',
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
