<?php

namespace App\Services\Stripe;

use App\Exceptions\ServiceException;
use Stripe\StripeClient as OfficialStripeClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared access to the official Stripe SDK client (secret key from config).
 * Use StripeClient::get()->accounts->create([...]) etc. per Stripe docs.
 */
final class StripeClient
{
    private static ?OfficialStripeClient $client = null;

    /**
     * Official Stripe client singleton for this request/process.
     */
    public static function get(): OfficialStripeClient
    {
        if (self::$client instanceof OfficialStripeClient) {
            return self::$client;
        }

        $secret = (string) config('stripe.secret', '');

        if ($secret === '') {
            throw new ServiceException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                __('messages.stripe_not_configured'),
            );
        }

        $options = [
            'api_key' => $secret,
        ];

        $apiVersion = config('stripe.api_version');

        if (filled($apiVersion)) {
            $options['stripe_version'] = (string) $apiVersion;
        }

        self::$client = new OfficialStripeClient($options);

        return self::$client;
    }

    /**
     * Reset the cached client (useful in tests).
     */
    public static function clear(): void
    {
        self::$client = null;
    }
}
