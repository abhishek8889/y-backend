<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\User;
use App\Services\TicketPdfService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;

test('ticket pdf embeds a PNG QR data URI for each issued ticket', function () {
    Storage::fake('local');

    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $user = User::factory()->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'QR Render Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'base_price' => 20.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $user->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'QR Customer',
        'customer_email' => 'qr@example.com',
        'paid_at' => now(),
    ]);

    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'quantity' => 1,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    $scanCode = 'YLQRTESTCODEABCDEFG123456';

    IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK'),
        'code' => $scanCode,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);

    $service = app(TicketPdfService::class);

    $qrSrc = (new ReflectionMethod(TicketPdfService::class, 'qrDataUriForCode'))
        ->invoke($service, $scanCode);

    expect($qrSrc)->toStartWith('data:image/png;base64,')
        ->and(base64_decode(substr($qrSrc, strlen('data:image/png;base64,')), true))->not->toBeFalse()
        ->and(substr(base64_decode(substr($qrSrc, strlen('data:image/png;base64,')), true), 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $result = $service->generateForOrder($order->fresh());

    expect($result['path'])->toBe('tickets/orders/'.$order->unique_id.'.pdf')
        ->and(Storage::disk('local')->exists($result['path']))->toBeTrue();

    $pdf = Storage::disk('local')->get($result['path']);

    expect($pdf)->toStartWith('%PDF')
        ->and(strlen($pdf))->toBeGreaterThan(2000);

    $order->refresh();
    expect($order->tickets_pdf_path)->toBe($result['path']);
});
