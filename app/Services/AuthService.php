<?php

namespace App\Services;

use App\Enum\StatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService extends Service
{
    public function __construct(private JwtTokenService $jwt) {}

    /**
     * @return array{user: User, access_token: string, token_type: string, expires_in: int}
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

        return [
            'user' => $user,
            'access_token' => $this->jwt->issue($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->expiresIn(),
        ];
    }
}
