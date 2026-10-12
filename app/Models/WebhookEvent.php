<?php

namespace App\Models;

use App\Enum\PaymentProviderEnum;
use App\Enum\WebhookEventStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PaymentProviderEnum $provider
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed>|null $payload
 * @property WebhookEventStatusEnum $status
 * @property int $attempts
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'provider',
    'event_id',
    'event_type',
    'payload',
    'status',
    'attempts',
    'received_at',
    'processed_at',
    'last_error',
])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProviderEnum::class,
            'payload' => 'array',
            'status' => WebhookEventStatusEnum::class,
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
