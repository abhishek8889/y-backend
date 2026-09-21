<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Temporary organiser registration pending email verification.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $country_code
 * @property string $country
 * @property string $password
 * @property string|null $otp
 * @property Carbon|null $otp_expired_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'first_name',
    'last_name',
    'email',
    'phone',
    'country_code',
    'country',
    'password',
    'otp',
    'otp_expired_at',
    'email_verified_at',
])]
#[Hidden(['password', 'otp'])]
class OrganiserRegistration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'otp_expired_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }
}
