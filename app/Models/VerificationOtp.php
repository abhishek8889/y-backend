<?php

namespace App\Models;

use App\Enum\VerificationOtpTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property VerificationOtpTypeEnum $type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $otp
 * @property Carbon $expire_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'email',
    'phone',
    'first_name',
    'last_name',
    'otp',
    'expire_at',
])]
class VerificationOtp extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VerificationOtpTypeEnum::class,
            'expire_at' => 'datetime',
        ];
    }
}
