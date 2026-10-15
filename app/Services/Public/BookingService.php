<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderSalesChannelEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PaymentProviderEnum;
use App\Enum\PaymentStatusEnum;
use App\Enum\PlatformSettingKeyEnum;
use App\Enum\StatusEnum;
use App\Models\EventTicketOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Service;
use App\Services\StripeService;
use App\Support\UniqueIdGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BookingService extends Service
{
    public function __construct(
        private readonly StripeService $stripe,
    ) {}

    /**
     * Start a public ticket booking checkout: hold inventory, create pending order, create PaymentIntent.
     *
     * @param  array{offers: list<array{offer_id: int, qty: int}>}  $data
     * @return array{
     *     order: array<string, mixed>,
     *     payment: array{
     *         payment_intent_id: string,
     *         client_secret: string|null,
     *         status: string,
     *         amount: string,
     *         currency: string
     *     }
     * }
     */
    public function startBookingTicket(User $user, array $data): array
    {
        $this->assertActiveUser($user);

        $validatedOffers = $this->validateBookableOffers($data['offers']);

        return DB::transaction(function () use ($user, $validatedOffers): array {
            // Sort IDs so concurrent multi-offer bookings always lock rows in the same order.
            $offerIds = collect($validatedOffers)
                ->pluck('offer_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            /** @var Collection<int, EventTicketOffer> $lockedOffers */
            $lockedOffers = EventTicketOffer::query()
                ->whereIn('id', $offerIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedOffers->load(['ticket.event.organisation']);

            $lineItems = [];
            $subtotal = 0.0;
            $platformFeeTotal = 0.0;
            $currency = null;
            $feeRate = $this->platformFeeRate();
            $primaryOrganisation = null;

            foreach ($validatedOffers as $item) {
                $offer = $lockedOffers->get($item['offer_id']);

                if ($offer === null) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.public_booking_offer_unavailable'),
                    );
                }

                $ticket = $offer->ticket;
                $event = $ticket?->event;
                $organisation = $event?->organisation;

                if ($ticket === null || $event === null || $organisation === null) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.public_booking_offer_unavailable'),
                    );
                }

                $lineCurrency = strtoupper((string) $ticket->currency);

                if ($currency === null) {
                    $currency = $lineCurrency;
                    $primaryOrganisation = $organisation;
                } elseif ($currency !== $lineCurrency) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.public_booking_mixed_currency'),
                    );
                }

                $qty = $item['qty'];
                $remaining = $this->remainingOfferQuantity($offer);

                if ($qty > $remaining) {
                    $this->fail(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        __('messages.public_booking_offer_quantity_exceeded'),
                    );
                }

                $unitPrice = round((float) $offer->price, 2);
                $lineTotal = round($unitPrice * $qty, 2);
                $platformShare = round($lineTotal * ($feeRate / 100), 2);
                $organiserShare = round($lineTotal - $platformShare, 2);

                $subtotal = round($subtotal + $lineTotal, 2);
                $platformFeeTotal = round($platformFeeTotal + $platformShare, 2);

                $lineItems[] = [
                    'event_id' => $event->id,
                    'organisation_id' => $organisation->id,
                    'event_ticket_id' => $ticket->id,
                    'ticket_offer_id' => $offer->id,
                    'ticket_name' => $ticket->name,
                    'offer_name' => $offer->name,
                    'quantity' => $qty,
                    'pricing_type' => OrderItemPricingTypeEnum::PAID,
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'discount_amount' => '0.00',
                    'total_price' => number_format($lineTotal, 2, '.', ''),
                    'platform_fee_rate' => number_format($feeRate, 2, '.', ''),
                    'platform_share_amount' => number_format($platformShare, 2, '.', ''),
                    'organiser_share_amount' => number_format($organiserShare, 2, '.', ''),
                    'currency' => $lineCurrency,
                ];
            }

            if ($primaryOrganisation === null || $currency === null) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.public_booking_offer_unavailable'),
                );
            }

            $discountAmount = 0.0;
            $totalAmount = round($subtotal - $discountAmount, 2);
            $holdMinutes = max(1, (int) config('booking.hold_minutes', 15));
            $expiresAt = now()->addMinutes($holdMinutes);

            $order = Order::query()->create([
                'unique_id' => $this->generateOrderUniqueId(),
                'checkout_key' => (string) Str::uuid(),
                'user_id' => $user->id,
                'coupon_id' => null,
                'coupon_code' => null,
                'status' => OrderStatusEnum::PENDING,
                'sales_channel' => OrderSalesChannelEnum::ONLINE,
                'subtotal' => number_format($subtotal, 2, '.', ''),
                'discount_amount' => number_format($discountAmount, 2, '.', ''),
                'platform_fee_amount' => number_format($platformFeeTotal, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => $currency,
                'customer_name' => trim($user->first_name.' '.$user->last_name),
                'customer_email' => $user->email,
                'customer_phone' => $user->phone,
                'expires_at' => $expiresAt,
                'created_by' => $user->id,
            ]);

            foreach ($lineItems as $lineItem) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    ...$lineItem,
                ]);
            }

            $idempotencyKey = 'order_'.$order->unique_id.'_payment';

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => PaymentProviderEnum::STRIPE,
                'status' => PaymentStatusEnum::PENDING,
                'amount' => number_format($totalAmount, 2, '.', ''),
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
            ]);

            $intent = $this->stripe->createPaymentIntent($primaryOrganisation, [
                'amount' => $totalAmount,
                'currency' => $currency,
                'idempotency_key' => $idempotencyKey,
                'receipt_email' => $user->email,
                'description' => 'Order '.$order->unique_id,
                'metadata' => [
                    'order_id' => (string) $order->id,
                    'order_unique_id' => $order->unique_id,
                    'checkout_key' => $order->checkout_key,
                    'user_id' => (string) $user->id,
                ],
            ]);

            if ($intent['client_secret'] === null) {
                $this->fail(
                    Response::HTTP_BAD_GATEWAY,
                    __('messages.stripe_api_error'),
                    'PaymentIntent client_secret missing.',
                );
            }

            $payment->update([
                'payment_intent_id' => $intent['id'],
                'status' => PaymentStatusEnum::PROCESSING,
            ]);

            $order->load('items');

            return [
                'order' => [
                    'unique_id' => $order->unique_id,
                    'checkout_key' => $order->checkout_key,
                    'status' => $order->status->value,
                    'subtotal' => (string) $order->subtotal,
                    'discount_amount' => (string) $order->discount_amount,
                    'platform_fee_amount' => (string) $order->platform_fee_amount,
                    'total_amount' => (string) $order->total_amount,
                    'currency' => $order->currency,
                    'expires_at' => $order->expires_at?->toIso8601String(),
                    'items' => $order->items->map(fn (OrderItem $item): array => [
                        'ticket_name' => $item->ticket_name,
                        'offer_name' => $item->offer_name,
                        'quantity' => $item->quantity,
                        'unit_price' => (string) $item->unit_price,
                        'total_price' => (string) $item->total_price,
                        'currency' => $item->currency,
                    ])->values()->all(),
                ],
                'payment' => [
                    'payment_intent_id' => $intent['id'],
                    'client_secret' => $intent['client_secret'],
                    'status' => PaymentStatusEnum::PROCESSING->value,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                ],
            ];
        });
    }

    private function assertActiveUser(User $user): void
    {
        if ($user->status !== StatusEnum::ACTIVE) {
            $this->fail(Response::HTTP_FORBIDDEN, __('auth.inactive'));
        }
    }

    /**
     * @param  list<array{offer_id: int, qty: int}>  $offers
     * @return list<array{offer_id: int, qty: int, offer: EventTicketOffer}>
     */
    private function validateBookableOffers(array $offers): array
    {
        $now = now();
        $offerIds = array_map(fn (array $item): int => (int) $item['offer_id'], $offers);

        /** @var Collection<int, EventTicketOffer> $bookableOffers */
        $bookableOffers = EventTicketOffer::query()
            ->whereIn('id', $offerIds)
            ->whereIn('status', [
                EventTicketOfferStatusEnum::ACTIVE,
                EventTicketOfferStatusEnum::SOLD_OUT,
            ])
            ->where(function ($query) use ($now): void {
                $query->whereNull('sale_starts_at')
                    ->orWhere('sale_starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('sale_ends_at')
                    ->orWhere('sale_ends_at', '>=', $now);
            })
            ->whereHas('ticket', function ($query): void {
                $query->whereIn('status', [
                    EventTicketStatusEnum::ACTIVE,
                    EventTicketStatusEnum::SOLD_OUT,
                ])->whereHas('event', function ($eventQuery): void {
                    $eventQuery->where('status', EventStatusEnum::PUBLISHED)
                        ->whereHas('organisation', function ($organisationQuery): void {
                            $organisationQuery->where('approve_status', true);
                        });
                });
            })
            ->with(['ticket.event'])
            ->get()
            ->keyBy('id');

        $validated = [];
        foreach ($offers as $item) {
            $offerId = (int) $item['offer_id'];
            $qty = (int) $item['qty'];
            $offer = $bookableOffers->get($offerId);

            if ($offer === null) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.public_booking_offer_unavailable'),
                );
            }

            if ($offer->status === EventTicketOfferStatusEnum::SOLD_OUT) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.public_booking_offer_sold_out'),
                );
            }

            if ($offer->max_per_order !== null && $qty > $offer->max_per_order) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.public_booking_offer_max_per_order_exceeded'),
                );
            }

            $validated[] = [
                'offer_id' => $offerId,
                'qty' => $qty,
                'offer' => $offer,
            ];
        }

        return $validated;
    }

    private function remainingOfferQuantity(EventTicketOffer $offer): int
    {
        $held = (int) OrderItem::consumedQuantitiesByOfferIds([$offer->id])
            ->get($offer->id, 0);

        return max(0, $offer->quantity_cap - $held);
    }

    private function generateOrderUniqueId(): string
    {
        do {
            $uniqueId = UniqueIdGenerator::generate('ORD', 12);
        } while (Order::query()->where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    private function platformFeeRate(): float
    {
        $value = PlatformSetting::getValue(PlatformSettingKeyEnum::BOOKING_PLATFORM_FEES_PERCENTAGE, 0);

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
