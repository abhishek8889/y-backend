<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Temporary organiser registration pending email verification / onboarding.
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
 * @property string|null $org_organiser_name
 * @property string|null $org_name
 * @property string|null $org_email
 * @property string|null $org_country_code
 * @property string|null $org_phone
 * @property string|null $org_country
 * @property string|null $org_city
 * @property string|null $org_address1
 * @property string|null $org_address2
 * @property string|null $org_postal_code
 * @property string|null $org_website
 * @property string|null $org_logo
 * @property string|null $org_banner
 * @property string|null $org_description
 * @property string|null $org_keywords
 * @property string|null $org_facebook_link
 * @property string|null $org_instagram_link
 * @property string|null $org_twitter_link
 * @property string|null $org_youtube_link
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
    'org_organiser_name',
    'org_name',
    'org_email',
    'org_country_code',
    'org_phone',
    'org_country',
    'org_city',
    'org_address1',
    'org_address2',
    'org_postal_code',
    'org_website',
    'org_logo',
    'org_banner',
    'org_description',
    'org_keywords',
    'org_facebook_link',
    'org_instagram_link',
    'org_twitter_link',
    'org_youtube_link',
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
