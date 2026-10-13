<?php

namespace App\Services;

use App\Enum\MailSenderEnum;
use App\Enum\OrderStatusEnum;
use App\Jobs\SendOrderConfirmationEmailJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\Venue;
use App\Models\VenueAddress;
use App\Support\MoneyFormatter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderConfirmationMailService extends Service
{
    public function __construct(
        private readonly MailService $mail,
        private readonly TicketPdfService $ticketPdf,
    ) {}

    /**
     * Queue confirmation email + tickets PDF after payment (fast webhook path).
     */
    public function queueAfterSuccessfulPayment(Order $order): void
    {
        if ($order->status !== OrderStatusEnum::PAID) {
            return;
        }

        if ($order->confirmation_email_sent_at !== null) {
            return;
        }

        if (! filled($order->customer_email)) {
            Log::channel('stripe_webhook')->warning('Order confirmation skipped: missing customer email', [
                'order_id' => $order->id,
                'order_unique_id' => $order->unique_id,
            ]);

            return;
        }

        SendOrderConfirmationEmailJob::dispatch($order->id)->afterCommit();
    }

    /**
     * Generate tickets PDF and queue the confirmation email (idempotent).
     * Intended to run inside SendOrderConfirmationEmailJob.
     */
    public function deliverForPaidOrder(int $orderId): void
    {
        $claimedOrder = DB::transaction(function () use ($orderId): ?Order {
            /** @var Order|null $order */
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                Log::channel('stripe_webhook')->warning('Order confirmation skipped: order not found', [
                    'order_id' => $orderId,
                ]);

                return null;
            }

            if ($order->status !== OrderStatusEnum::PAID) {
                return null;
            }

            if ($order->confirmation_email_sent_at !== null) {
                return null;
            }

            if (! filled($order->customer_email)) {
                Log::channel('stripe_webhook')->warning('Order confirmation skipped: missing customer email', [
                    'order_id' => $order->id,
                    'order_unique_id' => $order->unique_id,
                ]);

                return null;
            }

            // Claim before work so concurrent jobs / webhook retries cannot duplicate delivery.
            $order->forceFill([
                'confirmation_email_sent_at' => now(),
            ])->save();

            return $order;
        });

        if ($claimedOrder === null) {
            return;
        }

        try {
            $claimedOrder->load([
                'items.event.venue.address',
                'items.organisation:id,name',
                'issuedTickets.event.venue.address',
                'issuedTickets.organisation:id,name',
            ]);

            $pdf = $this->ticketPdf->generateForOrder($claimedOrder);
            $payload = $this->buildMailPayload($claimedOrder);

            $this->mail->send(
                to: $claimedOrder->customer_email,
                subject: __('messages.order_confirmation_mail_subject', [
                    'event' => $payload['primary_event_name'],
                ]),
                view: 'mail.order-confirmation',
                data: $payload,
                sentBy: MailSenderEnum::PLATFORM,
                attachments: [
                    [
                        'path' => $pdf['absolute_path'],
                        'name' => $pdf['filename'],
                        'mime' => 'application/pdf',
                    ],
                ],
            );
        } catch (\Throwable $exception) {
            Order::query()->whereKey($claimedOrder->id)->update([
                'confirmation_email_sent_at' => null,
            ]);

            Log::channel('stripe_webhook')->error('Order confirmation email failed', [
                'order_id' => $claimedOrder->id,
                'order_unique_id' => $claimedOrder->unique_id,
                'customer_email' => $claimedOrder->customer_email,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     customer_name: string,
     *     order_unique_id: string,
     *     currency: string,
     *     total_amount: string,
     *     formatted_total: string,
     *     primary_event_name: string,
     *     line_items: list<array{offer_name: string, quantity: int, unit_price: string, total_price: string, formatted_unit_price: string, formatted_total_price: string}>,
     *     events: list<array{name: string, starts_at: string|null, ends_at: string|null, venue_name: string|null, venue_address: string|null, organisation_name: string|null}>
     * }
     */
    private function buildMailPayload(Order $order): array
    {
        /** @var Collection<int, OrderItem> $items */
        $items = $order->items;

        $lineItems = $items->map(function (OrderItem $item) use ($order): array {
            $unitPrice = (string) $item->unit_price;
            $totalPrice = (string) $item->total_price;

            return [
                'offer_name' => (string) ($item->offer_name ?: $item->ticket_name),
                'quantity' => (int) $item->quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'formatted_unit_price' => MoneyFormatter::format($unitPrice, $order->currency),
                'formatted_total_price' => MoneyFormatter::format($totalPrice, $order->currency),
            ];
        })->values()->all();

        $events = $items
            ->groupBy('event_id')
            ->map(function (Collection $eventItems): array {
                /** @var OrderItem $first */
                $first = $eventItems->first();
                /** @var Event|null $event */
                $event = $first->event;
                /** @var Organisation|null $organisation */
                $organisation = $first->organisation;
                /** @var Venue|null $venue */
                $venue = $event?->venue;
                /** @var VenueAddress|null $address */
                $address = $venue?->address;
                $timezone = filled($event?->timezone) ? (string) $event->timezone : (string) config('app.timezone');

                return [
                    'name' => (string) ($event?->name ?? __('messages.order_confirmation_unknown_event')),
                    'starts_at' => $this->formatEventDateTime($event?->starts_at, $timezone),
                    'ends_at' => $this->formatEventDateTime($event?->ends_at, $timezone),
                    'venue_name' => $venue?->name,
                    'venue_address' => $this->formatVenueAddress($address),
                    'organisation_name' => $organisation?->name,
                ];
            })
            ->values()
            ->all();

        $primaryEventName = $events[0]['name'] ?? __('messages.order_confirmation_unknown_event');

        return [
            'customer_name' => (string) $order->customer_name,
            'order_unique_id' => (string) $order->unique_id,
            'currency' => (string) $order->currency,
            'total_amount' => (string) $order->total_amount,
            'formatted_total' => MoneyFormatter::format((string) $order->total_amount, $order->currency),
            'primary_event_name' => $primaryEventName,
            'line_items' => $lineItems,
            'events' => $events,
        ];
    }

    private function formatEventDateTime(?CarbonInterface $dateTime, string $timezone): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->copy()
            ->timezone($timezone)
            ->format('l, j F Y \a\t g:i A T');
    }

    private function formatVenueAddress(?VenueAddress $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $parts = array_values(array_filter([
            $address->address_line_1,
            $address->address_line_2,
            $address->city,
            $address->state,
            $address->postal_code,
            $address->country,
        ], fn (?string $part): bool => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
