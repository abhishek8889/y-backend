<?php

namespace App\Services;

use App\Exceptions\ServiceException;
use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Throwable;

class JwtTokenService
{
    /**
     * @param  array{scope?: string|null, roles?: list<string>, organisation_id?: int|null}  $context
     */
    public function issue(User $user, array $context = []): string
    {
        $now = now()->timestamp;

        return JWT::encode([
            'iss' => config('app.url'),
            'sub' => (string) $user->id,
            'scope' => $context['scope'] ?? null,
            'roles' => $context['roles'] ?? [],
            'organisation_id' => $context['organisation_id'] ?? null,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->expiresIn(),
            'jti' => (string) Str::uuid(),
        ], $this->secret(), $this->algo());
    }

    public function userId(string $token): int
    {
        $previousTimestamp = JWT::$timestamp;
        JWT::$timestamp = now()->timestamp;

        try {
            $payload = JWT::decode($token, new Key($this->secret(), $this->algo()));
        } catch (ServiceException $exception) {
            throw $exception;
        } catch (ExpiredException $exception) {
            throw ServiceException::unauthorized(__('auth.token_expired'), $exception);
        } catch (Throwable $exception) {
            throw ServiceException::unauthorized(__('auth.invalid_token'), $exception);
        } finally {
            JWT::$timestamp = $previousTimestamp;
        }

        return (int) $payload->sub;
    }

    public function expiresIn(): int
    {
        return (int) config('jwt.ttl') * 60;
    }

    private function secret(): string
    {
        $secret = config('jwt.secret');

        if (! is_string($secret) || $secret === '') {
            throw ServiceException::serverError(__('exceptions.jwt_not_configured'));
        }

        return $secret;
    }

    private function algo(): string
    {
        $algo = config('jwt.algo');

        return is_string($algo) && $algo !== '' ? $algo : 'HS256';
    }
}
