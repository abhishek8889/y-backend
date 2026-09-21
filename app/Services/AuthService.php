<?php

namespace App\Services;

use App\Enum\PlatformRoleEnum;
use App\Enum\RoleEnum;
use App\Enum\StatusEnum;
use App\Mail\NewOrganiserRegistrationMail;
use App\Mail\OrganiserRegistrationOtpMail;
use App\Models\Organisation;
use App\Models\OrganiserRegistration;
use App\Models\OrganiserStaff;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthService extends Service
{
    private const int OTP_EXPIRES_IN_MINUTES = 10;

    public function __construct(private JwtTokenService $jwt) {}

    /**
     * @return array{
     *     user: User,
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null
     * }
     */
    public function login(string $email, string $password): array
    {
        $email = Str::lower($email);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            $this->unprocessable(__('auth.failed'), [
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== StatusEnum::ACTIVE) {
            $this->forbidden(__('auth.inactive'));
        }

        $context = $user->loginContext();

        return [
            'user' => $user,
            'access_token' => $this->jwt->issue($user, $context),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->expiresIn(),
            ...$context,
        ];
    }

    /**
     * Store a temporary organiser registration and send OTP.
     *
     * @param  array<string, mixed>  $data
     * @return array{email: string}
     */
    public function registerOrganiser(array $data): array
    {
        $email = Str::lower($data['email']);

        if (User::query()->where('email', $email)->exists()) {
            $this->conflict(__('auth.email_already_registered'));
        }

        $otp = Str::upper(Str::random(6));

        $registration = OrganiserRegistration::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $data['phone'],
                'country_code' => $data['country_code'],
                'country' => $data['country'],
                'password' => $data['password'],
                'otp' => $otp,
                'otp_expired_at' => now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES),
                'email_verified_at' => null,
            ],
        );

        Mail::to($registration->email)->send(
            new OrganiserRegistrationOtpMail(
                $registration,
                $otp,
                self::OTP_EXPIRES_IN_MINUTES,
            ),
        );

        return [
            'email' => $registration->email,
        ];
    }

    /**
     * Verify organiser registration email OTP.
     *
     * @param  array{email: string, otp: string}  $data
     * @return array{
     *     user: User,
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null
     * }
     */
    public function verifyOrganiserEmail(array $data): array
    {
        $email = Str::lower($data['email']);
        $otp = Str::upper($data['otp']);

        $registration = OrganiserRegistration::query()->where('email', $email)->first();

        if ($registration === null) {
            $this->unprocessable(__('auth.registration_not_found'), [
                'email' => [__('auth.registration_not_found')],
            ]);
        }

        if ($registration->otp === null || trim($registration->otp) !== trim($otp)) {
            $this->unprocessable(__('auth.otp_invalid'), [
                'otp' => [__('auth.otp_invalid')],
            ]);
        }

        if ($registration->otp_expired_at === null || $registration->otp_expired_at->isPast()) {
            $this->unprocessable(__('auth.otp_expired'), [
                'otp' => [__('auth.otp_expired')],
            ]);
        }

        $registration->forceFill([
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expired_at' => null,
        ])->save();

        return $this->organiserOnboarding($registration);
    }

    /**
     * Create the organiser user/organisation after email verification.
     *
     * @return array{
     *     user: User,
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null
     * }
     */
    public function organiserOnboarding(OrganiserRegistration $registration): array
    {
        if (User::query()->where('email', $registration->email)->exists()) {
            $this->conflict(__('auth.email_already_registered'));
        }

        /** @var array{user: User, organisation: Organisation} $created */
        $created = DB::transaction(function () use ($registration): array {
            $user = User::query()->create([
                'first_name' => $registration->first_name,
                'last_name' => $registration->last_name,
                'email' => $registration->email,
                'phone' => $registration->phone,
                'country_code' => $registration->country_code,
                'country' => $registration->country,
                'password' => $registration->getRawOriginal('password'),
                'status' => StatusEnum::ACTIVE,
                'email_verified_at' => $registration->email_verified_at ?? now(),
            ]);

            $displayName = trim($registration->first_name.' '.$registration->last_name);

            $organisation = Organisation::query()->create([
                'owner_id' => $user->id,
                'unique_id' => 'ORG' . strtoupper(substr((string) Str::ulid(), 0, 5)),
                'organiser_name' => $displayName,
                'name' => $displayName,
                'email' => $registration->email,
                'country_code' => $registration->country_code,
                'phone' => $registration->phone,
                'country' => $registration->country,
                'complete_status' => true,
                'approve_status' => false,
            ]);

            $role = Role::query()->create([
                'organisation_id' => $organisation->id,
                'name' => RoleEnum::OWNER->value,
            ]);

            $staff = OrganiserStaff::query()->create([
                'organisation_id' => $organisation->id,
                'user_id' => $user->id,
                'status' => StatusEnum::ACTIVE,
            ]);

            $staff->assignRole($role);

            $registration->delete();

            return [
                'user' => $user,
                'organisation' => $organisation,
            ];
        });

        $this->notifySuperAdmins($created['user'], $created['organisation']);

        $context = $created['user']->loginContext();

        return [
            'user' => $created['user'],
            'access_token' => $this->jwt->issue($created['user'], $context),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->expiresIn(),
            ...$context,
        ];
    }

    private function notifySuperAdmins(User $organiser, Organisation $organisation): void
    {
        $superAdminEmails = User::query()
            ->whereHas(
                'platformStaff',
                fn ($query) => $query
                    ->where('status', StatusEnum::ACTIVE)
                    ->whereHas(
                        'roles',
                        fn ($roles) => $roles->where('name', PlatformRoleEnum::SUPER_ADMIN->value),
                    ),
            )
            ->pluck('email')
            ->filter()
            ->all();

        if ($superAdminEmails === []) {
            return;
        }

        Mail::to($superAdminEmails)->send(
            new NewOrganiserRegistrationMail($organiser, $organisation),
        );
    }
}
