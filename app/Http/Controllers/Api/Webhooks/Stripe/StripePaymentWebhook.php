<?php

namespace App\Http\Controllers\Api\Webhooks\Stripe;

use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\WebhookEventStatusEnum;
use App\Exceptions\ServiceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Services\IssuedTicketService;
use App\Services\OrderConfirmationMailService;
use App\Services\OrganiserBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stripe PaymentIntent webhooks (POST /api/stripe/payment-webhooks).
 *
 * Handled events:
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - payment_intent.canceled
 */
class StripePaymentWebhook extends Controller
{
    public function __construct(
        private readonly OrganiserBalanceService $organiserBalances,
        private readonly IssuedTicketService $issuedTickets,
        private readonly OrderConfirmationMailService $orderConfirmations,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->parseEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );

            Log::channel('stripe_webhook')->info('Payment webhook received', [
                'event_id' => $event['id'],
                'type' => $event['type'],
            ]);

            $handled = $this->dispatch($event);

            Log::channel('stripe_webhook')->info('Payment webhook handled', [
                'event_id' => $event['id'],
                'type' => $event['type'],
                'handled' => $handled,
            ]);

            return $this->success(__('messages.stripe_webhook_received'), [
                'type' => $event['type'],
                'handled' => $handled,
            ]);
        } catch (Throwable $exception) {
            Log::channel('stripe_webhook')->error('Payment webhook failed', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failed($exception);
        }
    }

    /**
     * @param  array{id: string, type: string, data: array<string, mixed>}  $event
     */
    private function dispatch(array $event): bool
    {
        $webhookEvent = $this->claimWebhookEvent($event);

        if ($webhookEvent === null) {
            return true;
        }

        try {
            $handled = match ($event['type']) {
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event['data']),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event['data']),
                'payment_intent.canceled' => $this->handlePaymentIntentCanceled($event['data']),
                default => false,
            };

            $webhookEvent->update([
                'status' => WebhookEventStatusEnum::PROCESSED,
                'processed_at' => Carbon::now(),
            ]);

            return $handled;
        } catch (Throwable $exception) {
            $webhookEvent->update([
                'status' => WebhookEventStatusEnum::FAILED,
                'last_error' => $exception->getMessage(),
                'attempts' => $webhookEvent->attempts + 1,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array{id: string, type: string, data: array<string, mixed>}  $event
     */
    private function claimWebhookEvent(array $event): ?WebhookEvent
    {
        $existing = WebhookEvent::query()
            ->where('provider', PaymentProviderEnum::STRIPE)
            ->where('event_id', $event['id'])
            ->first();

        if ($existing !== null) {
            if ($existing->status === WebhookEventStatusEnum::PROCESSED) {
                return null;
            }

            $existing->update([
                'status' => WebhookEventStatusEnum::PROCESSING,
                'attempts' => $existing->attempts + 1,
                'payload' => $event['data'],
                'last_error' => null,
            ]);

            return $existing->fresh();
        }

        return WebhookEvent::query()->create([
            'provider' => PaymentProviderEnum::STRIPE,
            'event_id' => $event['id'],
            'event_type' => $event['type'],
            'payload' => $event['data'],
            'status' => WebhookEventStatusEnum::PROCESSING,
            'attempts' => 1,
            'received_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function handlePaymentIntentSucceeded(array $paymentIntent): bool
    {
        $payment = $this->findPayment($paymentIntent);

        if ($payment === null) {
            return false;
        }

        $order = DB::transaction(function () use ($payment, $paymentIntent): Order {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if (! ($payment->status === PaymentStatusEnum::SUCCEEDED
                && $order->status === OrderStatusEnum::PAID)) {
                $paidAt = Carbon::now();

                $payment->update([
                    'status' => PaymentStatusEnum::SUCCEEDED,
                    'charge_id' => $this->resolveChargeId($paymentIntent),
                    'payment_method' => $this->resolvePaymentMethod($paymentIntent),
                    'failure_reason' => null,
                    'paid_at' => $paidAt,
                ]);

                if ($order->status !== OrderStatusEnum::PAID) {
                    $order->update([
                        'status' => OrderStatusEnum::PAID,
                        'paid_at' => $paidAt,
                        'cancelled_at' => null,
                    ]);
                }

                $this->organiserBalances->creditReservedEarnings($order);
            }

            $order = $order->fresh() ?? $order;
            $this->issuedTickets->issueForPaidOrder($order);

            return $order;
        });

        // PDF generation + email run async after commit (keeps webhook fast).
        $this->orderConfirmations->queueAfterSuccessfulPayment($order);

        return true;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function handlePaymentIntentFailed(array $paymentIntent): bool
    {
        $payment = $this->findPayment($paymentIntent);

        if ($payment === null) {
            return false;
        }

        if ($payment->status === PaymentStatusEnum::SUCCEEDED) {
            return true;
        }

        $payment->update([
            'status' => PaymentStatusEnum::FAILED,
            'failure_reason' => $this->resolveFailureReason($paymentIntent),
            'payment_method' => $this->resolvePaymentMethod($paymentIntent) ?? $payment->payment_method,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function handlePaymentIntentCanceled(array $paymentIntent): bool
    {
        $payment = $this->findPayment($paymentIntent);

        if ($payment === null) {
            return false;
        }

        return DB::transaction(function () use ($payment): bool {
            /** @var Payment $payment */
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

            if ($payment->status === PaymentStatusEnum::SUCCEEDED
                || $order->status === OrderStatusEnum::PAID) {
                return true;
            }

            $payment->update([
                'status' => PaymentStatusEnum::CANCELLED,
            ]);

            if ($order->status === OrderStatusEnum::PENDING) {
                $order->update([
                    'status' => OrderStatusEnum::CANCELLED,
                    'cancelled_at' => Carbon::now(),
                ]);
            }

            return true;
        });
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function findPayment(array $paymentIntent): ?Payment
    {
        $paymentIntentId = (string) ($paymentIntent['id'] ?? '');

        if ($paymentIntentId === '') {
            return null;
        }

        $payment = Payment::query()
            ->where('payment_intent_id', $paymentIntentId)
            ->first();

        if ($payment === null) {
            Log::channel('stripe_webhook')->warning('Local payment not found for PaymentIntent', [
                'payment_intent_id' => $paymentIntentId,
            ]);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function resolveChargeId(array $paymentIntent): ?string
    {
        $latestCharge = $paymentIntent['latest_charge'] ?? null;

        if (is_string($latestCharge) && $latestCharge !== '') {
            return $latestCharge;
        }

        if (is_array($latestCharge) && filled($latestCharge['id'] ?? null)) {
            return (string) $latestCharge['id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function resolvePaymentMethod(array $paymentIntent): ?string
    {
        $paymentMethod = $paymentIntent['payment_method'] ?? null;

        if (is_string($paymentMethod) && $paymentMethod !== '') {
            return $paymentMethod;
        }

        if (is_array($paymentMethod) && filled($paymentMethod['id'] ?? null)) {
            return (string) $paymentMethod['id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $paymentIntent
     */
    private function resolveFailureReason(array $paymentIntent): ?string
    {
        $error = $paymentIntent['last_payment_error'] ?? null;

        if (! is_array($error)) {
            return null;
        }

        $message = $error['message'] ?? $error['code'] ?? null;

        return filled($message) ? (string) $message : null;
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}
     */
    private function parseEvent(string $payload, string $signatureHeader): array
    {
        $secret = (string) config('stripe.payment_webhook_secret', '');

        if ($secret === '') {
            throw new ServiceException(
                Response::HTTP_SERVICE_UNAVAILABLE,
                __('messages.stripe_webhook_not_configured'),
            );
        }

        if ($signatureHeader === '') {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_signature'),
            );
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $secret);
        } catch (\UnexpectedValueException $exception) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_payload'),
                $exception->getMessage(),
            );
        } catch (SignatureVerificationException $exception) {
            throw new ServiceException(
                Response::HTTP_BAD_REQUEST,
                __('messages.stripe_webhook_invalid_signature'),
                $exception->getMessage(),
            );
        }

        return [
            'id' => (string) $event->id,
            'type' => (string) $event->type,
            'data' => $this->toArray($event->data->object),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(mixed $object): array
    {
        if ($object instanceof StripeObject) {
            return $object->toArray();
        }

        if (is_array($object)) {
            return $object;
        }

        return [];
    }
}
