<?php

namespace App\Services;

use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IssuedTicketService extends Service
{
    /**
     * Issue one scannable ticket per purchased quantity for a paid order.
     * Idempotent: safe to call again on Stripe webhook retries.
     *
     * @return list<IssuedTicket>
     */
    public function issueForPaidOrder(Order $order): array
    {
        if ($order->status !== OrderStatusEnum::PAID) {
            return [];
        }

        $order->loadMissing('items');

        $issued = [];

        foreach ($order->items as $item) {
            $existingCount = IssuedTicket::query()
                ->where('order_item_id', $item->id)
                ->count();

            $remaining = max(0, (int) $item->quantity - $existingCount);

            for ($index = 0; $index < $remaining; $index++) {
                $issued[] = $this->createIssuedTicket($order, $item);
            }
        }

        return $issued;
    }

    private function createIssuedTicket(Order $order, OrderItem $item): IssuedTicket
    {
        $now = Carbon::now();

        return IssuedTicket::query()->create([
            'unique_id' => $this->generateUniqueId(),
            'code' => $this->generateScanCode(),
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'organisation_id' => $item->organisation_id,
            'event_id' => $item->event_id,
            'event_ticket_id' => $item->event_ticket_id,
            'ticket_offer_id' => $item->ticket_offer_id,
            'ticket_name' => $item->ticket_name,
            'offer_name' => $item->offer_name,
            'status' => IssuedTicketStatusEnum::VALID,
            'status_updated_at' => $now,
        ]);
    }

    private function generateUniqueId(): string
    {
        do {
            $uniqueId = UniqueIdGenerator::generate('ITK', 12);
        } while (IssuedTicket::query()->where('unique_id', $uniqueId)->exists());

        return $uniqueId;
    }

    private function generateScanCode(): string
    {
        do {
            $code = 'YL'.strtoupper(Str::random(24));
        } while (IssuedTicket::query()->where('code', $code)->exists());

        return $code;
    }
}
