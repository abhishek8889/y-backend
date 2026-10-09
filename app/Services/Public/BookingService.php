<?php

namespace App\Services\Public;

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\StatusEnum;
use App\Models\EventTicketOffer;
use App\Models\User;
use App\Services\Service;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

class BookingService extends Service
{
    /**
     * Start a public ticket booking checkout.
     *
     * @param  array{offers: list<array{offer_id: int, qty: int}>}  $data
     * @return array{offers: list<array{offer_id: int, qty: int}>}
     */
    public function startBookingTicket(User $user, array $data): array
    {
        $this->assertActiveUser($user);

        $validatedOffers = $this->validateBookableOffers($data['offers']);

        // TODO: create pending order + PaymentIntent.

        return [
            'offers' => array_map(
                fn (array $item): array => [
                    'offer_id' => $item['offer_id'],
                    'qty' => $item['qty'],
                ],
                $validatedOffers,
            ),
        ];
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

            if ($qty > $offer->quantity_cap) {
                $this->fail(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('messages.public_booking_offer_quantity_exceeded'),
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
}
