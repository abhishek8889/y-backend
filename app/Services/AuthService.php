<?php

namespace App\Services;

use App\Enum\MailSenderEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\PlatformRoleEnum;
use App\Enum\StatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserRegistration;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Services\Organisation\OrganisationRoleService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthService extends Service
{
    private const int OTP_EXPIRES_IN_MINUTES = 10;

    public function __construct(
        private JwtTokenService $jwt,
        private MailService $mail,
        private OrganisationRoleService $organisationRoles,
    ) {}

    /**
     * @return array{
     *     user: User,
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null,
     *     permissions: array<string, list<string>>
     * }
     */
    public function login(string $email, string $password): array
    {
        $email = Str::lower($email);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->getAuthPassword())) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.failed'));
        }

        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $context = $user->organisationLoginContext();

        if ($context === null || $context['scope'] !== PermissionScopeEnum::ORGANISATION->value) {
            $this->fail(
                Response::HTTP_FORBIDDEN,
                __('auth.organisation_login_forbidden'),
            );
        }

        return $this->authPayload($user, $context);
    }

    /**
     * Resolve the authenticated user's current scope, roles, and permissions.
     *
     * @return array{
     *     user: User,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null,
     *     permissions: array<string, list<string>>,
     *     organisation_approve_status?: bool
     * }
     */
    public function aboutMe(User $user): array
    {
        $context = $user->organisationLoginContext() ?? $user->loginContext();

        $payload = [
            'user' => $user,
            ...$context,
            'permissions' => $user->permissionsGroupedByModule($context),
        ];

        $organisationId = $context['organisation_id'] ?? null;

        if ($organisationId !== null) {
            $organisation = Organisation::query()->find($organisationId);

            if ($organisation !== null && $user->owns($organisation)) {
                $payload['organisation_approve_status'] = (bool) $organisation->approve_status;
            }
        }

        return $payload;
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
            $this->fail(Response::HTTP_CONFLICT, __('auth.email_already_registered'));
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
                'org_organiser_name' => $data['org_organiser_name'] ?? null,
                'org_name' => $data['org_name'],
                'org_email' => $data['org_email'] ?? null,
                'org_country_calling_code' => $data['org_country_calling_code'],
                'org_country_code' => $data['org_country_code'],
                'org_phone' => $data['org_phone'] ?? null,
                'org_country' => $data['org_country'] ?? null,
                'org_city' => $data['org_city'] ?? null,
                'org_address1' => $data['org_address1'] ?? null,
                'org_address2' => $data['org_address2'] ?? null,
                'org_postal_code' => $data['org_postal_code'] ?? null,
                'org_website' => $data['org_website'] ?? null,
                'org_logo' => $data['org_logo'] ?? null,
                'org_banner' => $data['org_banner'] ?? null,
                'org_description' => $data['org_description'] ?? null,
                'org_keywords' => $data['org_keywords'] ?? null,
                'org_facebook_link' => $data['org_facebook_link'] ?? null,
                'org_instagram_link' => $data['org_instagram_link'] ?? null,
                'org_twitter_link' => $data['org_twitter_link'] ?? null,
                'org_youtube_link' => $data['org_youtube_link'] ?? null,
                'otp' => $otp,
                'otp_expired_at' => now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES),
                'email_verified_at' => null,
            ],
        );

        $this->mail->send(
            to: $registration->email,
            subject: __('auth.otp_mail_subject'),
            view: 'mail.organiser-registration-otp',
            data: [
                'registration' => $registration,
                'otp' => $otp,
                'otpExpiresInMinutes' => self::OTP_EXPIRES_IN_MINUTES,
            ],
            sentBy: MailSenderEnum::PLATFORM,
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
     *     organisation_id: int|null,
     *     permissions: array<string, list<string>>
     * }
     */
    public function verifyOrganiserEmail(array $data): array
    {
        $email = Str::lower($data['email']);
        $otp = Str::upper($data['otp']);

        $registration = OrganiserRegistration::query()->where('email', $email)->first();

        if ($registration === null) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.registration_not_found'));
        }

        if ($registration->otp === null || trim($registration->otp) !== trim($otp)) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.otp_invalid'));
        }

        if ($registration->otp_expired_at === null || $registration->otp_expired_at->isPast()) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.otp_expired'));
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
     *     organisation_id: int|null,
     *     permissions: array<string, list<string>>
     * }
     */
    public function organiserOnboarding(OrganiserRegistration $registration): array
    {
        if (User::query()->where('email', $registration->email)->exists()) {
            $this->fail(Response::HTTP_CONFLICT, __('auth.email_already_registered'));
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

            // Check random string on organisation table

            $uniqueId = UniqueIdGenerator::generate('ORG');
            while (Organisation::query()->where('unique_id', $uniqueId)->exists()) {
                $uniqueId = UniqueIdGenerator::generate('ORG');
            }

            $organisation = Organisation::query()->create([
                'owner_id' => $user->id,
                'unique_id' => $uniqueId,
                'organiser_name' => $registration->org_organiser_name ?? $displayName,
                'name' => $registration->org_name ?? $displayName,
                'email' => $registration->org_email ?? $registration->email,
                'country_calling_code' => $registration->org_country_calling_code ?? $registration->country_code,
                'country_code' => $registration->org_country_code,
                'phone' => $registration->org_phone ?? $registration->phone,
                'country' => $registration->org_country ?? $registration->country,
                'city' => $registration->org_city,
                'address1' => $registration->org_address1,
                'address2' => $registration->org_address2,
                'postal_code' => $registration->org_postal_code,
                'website' => $registration->org_website,
                'logo' => $registration->org_logo,
                'banner' => $registration->org_banner,
                'description' => $registration->org_description,
                'keywords' => $registration->org_keywords,
                'facebook_link' => $registration->org_facebook_link,
                'instagram_link' => $registration->org_instagram_link,
                'twitter_link' => $registration->org_twitter_link,
                'youtube_link' => $registration->org_youtube_link,
                'complete_status' => true,
                'approve_status' => false,
            ]);

            $role = $this->organisationRoles->provisionDefaultRoles($organisation);

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

        return $this->authPayload($created['user']);
    }

    /**
     * @param  array{scope: string|null, roles: list<string>, organisation_id: int|null}|null  $context
     * @return array{
     *     user: User,
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string|null,
     *     roles: list<string>,
     *     organisation_id: int|null,
     *     permissions: array<string, list<string>>
     * }
     */
    private function authPayload(User $user, ?array $context = null): array
    {
        $context ??= $user->loginContext();

        return [
            'user' => $user,
            'access_token' => $this->jwt->issue($user, $context),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->expiresIn(),
            ...$context,
            'permissions' => $user->permissionsGroupedByModule($context),
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

        $this->mail->send(
            to: $superAdminEmails,
            subject: __('auth.new_organiser_mail_subject'),
            view: 'mail.new-organiser-registration',
            data: [
                'organiser' => $organiser,
                'organisation' => $organisation,
            ],
            sentBy: MailSenderEnum::PLATFORM,
        );
    }
}
