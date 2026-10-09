<?php

namespace App\Services\Public;

use App\Enum\MailSenderEnum;
use App\Enum\PermissionScopeEnum;
use App\Enum\StatusEnum;
use App\Enum\VerificationOtpTypeEnum;
use App\Models\User;
use App\Models\VerificationOtp;
use App\Services\JwtTokenService;
use App\Services\MailService;
use App\Services\Service;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CustomerAuthService extends Service
{
    private const int OTP_EXPIRES_IN_MINUTES = 10;

    private const string CUSTOMER_ROLE = 'Customer';

    public function __construct(
        private JwtTokenService $jwt,
        private MailService $mail,
    ) {}

    /**
     * Store a login OTP and email the code. User is created only on verify.
     *
     * @param  array{email: string, phone: string, first_name?: string|null, last_name?: string|null}  $data
     * @return array{email: string}
     */
    public function sendLoginOtp(array $data): array
    {
        $email = Str::lower($data['email']);
        $phone = trim($data['phone']);
        $firstName = isset($data['first_name']) ? trim((string) $data['first_name']) : '';
        $lastName = isset($data['last_name']) ? trim((string) $data['last_name']) : '';

        if ($firstName === '') {
            $localPart = Str::before($email, '@');
            $firstName = filled($localPart) ? $localPart : 'Customer';
        }

        $lastName = $lastName !== '' ? $lastName : null;

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }

        $plainOtp = Str::upper(Str::random(6));

        VerificationOtp::query()
            ->where('type', VerificationOtpTypeEnum::LOGIN_TYPE)
            ->where('email', $email)
            ->delete();

        VerificationOtp::query()->create([
            'type' => VerificationOtpTypeEnum::LOGIN_TYPE,
            'email' => $email,
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'otp' => Hash::make($plainOtp),
            'expire_at' => now()->addMinutes(self::OTP_EXPIRES_IN_MINUTES),
        ]);

        $greetingName = $firstName;

        $this->mail->send(
            to: $email,
            subject: __('auth.login_otp_mail_subject'),
            view: 'mail.customer-login-otp',
            data: [
                'name' => $greetingName,
                'otp' => $plainOtp,
                'otpExpiresInMinutes' => self::OTP_EXPIRES_IN_MINUTES,
            ],
            sentBy: MailSenderEnum::PLATFORM,
        );

        return [
            'email' => $email,
        ];
    }

    /**
     * Verify login OTP, create the user when needed, and return a JWT without permissions.
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
    public function verifyLoginOtp(array $data): array
    {
        $email = Str::lower($data['email']);
        $otp = Str::upper($data['otp']);

        $verification = VerificationOtp::query()
            ->where('type', VerificationOtpTypeEnum::LOGIN_TYPE)
            ->where('email', $email)
            ->latest('id')
            ->first();

        if ($verification === null || ! Hash::check($otp, $verification->otp)) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.otp_invalid'));
        }

        if ($verification->expire_at->isPast()) {
            $this->fail(Response::HTTP_UNPROCESSABLE_ENTITY, __('auth.otp_expired'));
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'first_name' => filled($verification->first_name) ? $verification->first_name : 'Customer',
                'last_name' => filled($verification->last_name) ? $verification->last_name : 'Customer',
                'email' => $email,
                'phone' => $verification->phone,
                'password' => Str::password(32),
                'status' => StatusEnum::ACTIVE,
                'email_verified_at' => now(),
            ]);
        } else {
            if ($user->status !== StatusEnum::ACTIVE) {
                $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
            }

            $updates = [];

            if ($user->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }

            if (filled($verification->phone) && $user->phone !== $verification->phone) {
                $updates['phone'] = $verification->phone;
            }

            if ($updates !== []) {
                $user->forceFill($updates)->save();
            }
        }

        $verification->delete();

        VerificationOtp::query()
            ->where('type', VerificationOtpTypeEnum::LOGIN_TYPE)
            ->where('email', $email)
            ->delete();

        $context = $this->resolveLoginContext($user);

        return [
            'user' => $user->fresh(),
            'access_token' => $this->jwt->issue($user, $context),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->expiresIn(),
            ...$context,
        ];
    }

    /**
     * @return array{scope: string|null, roles: list<string>, organisation_id: int|null}
     */
    private function resolveLoginContext(User $user): array
    {
        $platformContext = $user->platformLoginContext();

        if ($platformContext !== null) {
            return $platformContext;
        }

        $organisationContext = $user->organisationLoginContext();

        if ($organisationContext !== null) {
            return $organisationContext;
        }

        return [
            'scope' => PermissionScopeEnum::CUSTOMER->value,
            'roles' => [self::CUSTOMER_ROLE],
            'organisation_id' => null,
        ];
    }
}
