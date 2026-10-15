<?php

namespace App\Services;

use App\Enum\MailSenderEnum;
use App\Enum\RefundStatusEnum;
use App\Jobs\SendOrderRefundEmailJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderRefundMailService extends Service
{
    public const EXPECTED_DAYS = 10;

    public function __construct(
        private readonly MailService $mail,
    ) {}

    /**
     * Queue customer refund notification after a successful refund settlement.
     */
    public function queueAfterSuccessfulRefund(Refund $refund): void
    {
        if ($refund->status !== RefundStatusEnum::SUCCEEDED) {
            return;
        }

        if ($refund->customer_notified_at !== null) {
            return;
        }

        SendOrderRefundEmailJob::dispatch($refund->id)->afterCommit();
    }

    /**
     * Build and send the customer refund email (idempotent).
     */
    public function deliverForSucceededRefund(int $refundId): void
    {
        $claimedRefund = DB::transaction(function () use ($refundId): ?Refund {
            /** @var Refund|null $refund */
            $refund = Refund::query()
                ->whereKey($refundId)
                ->lockForUpdate()
                ->first();

            if ($refund === null) {
                Log::warning('Order refund email skipped: refund not found', [
                    'refund_id' => $refundId,
                ]);

                return null;
            }

            if ($refund->status !== RefundStatusEnum::SUCCEEDED) {
                return null;
            }

            if ($refund->customer_notified_at !== null) {
                return null;
            }

            $refund->loadMissing('order');

            if (! filled($refund->order?->customer_email)) {
                Log::warning('Order refund email skipped: missing customer email', [
                    'refund_id' => $refund->id,
                    'order_id' => $refund->order_id,
                ]);

                return null;
            }

            $refund->forceFill([
                'customer_notified_at' => now(),
            ])->save();

            return $refund;
        });

        if ($claimedRefund === null) {
            return;
        }

        try {
            $claimedRefund->load([
                'order.items.event:id,name,unique_id,starts_at,timezone',
                'payment:id,provider,amount,currency',
            ]);

            $payload = $this->buildMailPayload($claimedRefund);

            $this->mail->send(
                to: (string) $claimedRefund->order->customer_email,
                subject: __('messages.order_refund_mail_subject', [
                    'order' => $payload['order_unique_id'],
                ]),
                view: 'mail.order-refund',
                data: $payload,
                sentBy: MailSenderEnum::PLATFORM,
            );
        } catch (\Throwable $exception) {
            Refund::query()->whereKey($claimedRefund->id)->update([
                'customer_notified_at' => null,
            ]);

            Log::error('Order refund email failed', [
                'refund_id' => $claimedRefund->id,
                'order_id' => $claimedRefund->order_id,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMailPayload(Refund $refund): array
    {
        /** @var Order $order */
        $order = $refund->order;

        $tickets = $order->items->map(function (OrderItem $item): array {
            $event = $item->event;

            return [
                'ticket_id' => $item->event_ticket_id,
                'ticket_name' => (string) $item->ticket_name,
                'offer_name' => $item->offer_name,
                'quantity' => (int) $item->quantity,
                'event_id' => $item->event_id,
                'event_name' => (string) ($event?->name ?? __('messages.order_confirmation_unknown_event')),
                'event_unique_id' => $event?->unique_id,
            ];
        })->values()->all();

        $primaryEventName = $tickets[0]['event_name']
            ?? __('messages.order_confirmation_unknown_event');

        return [
            'customer_name' => (string) $order->customer_name,
            'order_id' => $order->id,
            'order_unique_id' => (string) $order->unique_id,
            'refund_id' => $refund->id,
            'refund_unique_id' => (string) $refund->unique_id,
            'refund_amount' => (string) $refund->amount,
            'formatted_refund_amount' => MoneyFormatter::format((string) $refund->amount, (string) $refund->currency),
            'currency' => (string) $refund->currency,
            'refund_reason' => $refund->reason,
            'expected_days' => self::EXPECTED_DAYS,
            'payment_provider' => $refund->payment?->provider?->value,
            'primary_event_name' => $primaryEventName,
            'tickets' => $tickets,
        ];
    }
}
