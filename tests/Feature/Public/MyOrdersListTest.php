<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\IssuedTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\IssuedTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueAddress;
use App\Services\JwtTokenService;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Str;

function myOrdersCustomerToken(User $user): string
{
    return app(JwtTokenService::class)->issue($user, [
        'scope' => PermissionScopeEnum::CUSTOMER->value,
        'roles' => ['Customer'],
        'organisation_id' => null,
    ]);
}

/**
 * @return array{customer: User, order: Order, scan_code: string}
 */
function paidOrderWithIssuedTicketFixture(User $customer): array
{
    $organisation = Organisation::factory()->create(['approve_status' => true]);
    $owner = User::factory()->create();

    $venue = Venue::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('VEN'),
        'name' => 'ABC Venue',
        'status' => 'active',
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    VenueAddress::query()->create([
        'venue_id' => $venue->id,
        'address_line_1' => '1 Test Street',
        'city' => 'London',
        'state' => 'Greater London',
        'postal_code' => 'E1 1AA',
        'country' => 'United Kingdom',
        'latitude' => 51.5074,
        'longitude' => -0.1278,
    ]);

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Live Music Event',
        'venue_id' => $venue->id,
        'starts_at' => now()->addDays(5)->setTime(19, 0),
        'ends_at' => now()->addDays(5)->setTime(23, 0),
        'timezone' => 'Europe/London',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
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
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'paid_at' => now(),
    ]);

    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'offer_name' => 'Early Bird',
        'quantity' => 1,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    $scanCode = 'YL'.strtoupper(Str::random(24));

    IssuedTicket::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ITK'),
        'code' => $scanCode,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'organisation_id' => $organisation->id,
        'event_id' => $event->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'offer_name' => 'Early Bird',
        'status' => IssuedTicketStatusEnum::VALID,
        'status_updated_at' => now(),
    ]);

    return [
        'customer' => $customer,
        'order' => $order,
        'scan_code' => $scanCode,
    ];
}

test('my orders list requires authentication', function () {
    test()->getJson('/api/public/my-orders-list')->assertUnauthorized();
});

test('authenticated customer can list paid orders with event venue and scan codes', function () {
    $customer = User::factory()->create();
    ['order' => $order, 'scan_code' => $scanCode] = paidOrderWithIssuedTicketFixture($customer);

    test()->withToken(myOrdersCustomerToken($customer))
        ->getJson('/api/public/my-orders-list')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.my_orders_list'))
        ->assertJsonPath('data.items.0.unique_id', $order->unique_id)
        ->assertJsonPath('data.items.0.status', OrderStatusEnum::PAID->value)
        ->assertJsonPath('data.items.0.formatted_total', '£20.00')
        ->assertJsonPath('data.items.0.currency_sign', '£')
        ->assertJsonPath('data.items.0.items.0.ticket_name', 'General Admission')
        ->assertJsonPath('data.items.0.items.0.offer_name', 'Early Bird')
        ->assertJsonPath('data.items.0.items.0.event.name', 'Live Music Event')
        ->assertJsonPath('data.items.0.items.0.event.venue.name', 'ABC Venue')
        ->assertJsonPath('data.items.0.items.0.event.venue.address.city', 'London')
        ->assertJsonPath('data.items.0.items.0.event.venue.address.address_line_1', '1 Test Street')
        ->assertJsonPath('data.items.0.items.0.issued_tickets.0.code', $scanCode)
        ->assertJsonPath('data.items.0.items.0.issued_tickets.0.status', IssuedTicketStatusEnum::VALID->value)
        ->assertJsonStructure([
            'data' => [
                'items',
                'pagination' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'has_more',
                ],
            ],
        ]);
});

test('my orders list only returns the authenticated customer orders', function () {
    $customer = User::factory()->create();
    $otherCustomer = User::factory()->create();

    paidOrderWithIssuedTicketFixture($customer);
    ['order' => $otherOrder] = paidOrderWithIssuedTicketFixture($otherCustomer);

    $response = test()->withToken(myOrdersCustomerToken($customer))
        ->getJson('/api/public/my-orders-list')
        ->assertOk();

    $uniqueIds = collect($response->json('data.items'))->pluck('unique_id');

    expect($uniqueIds)->toHaveCount(1)
        ->and($uniqueIds)->not->toContain($otherOrder->unique_id);
});

test('my orders list excludes pending orders', function () {
    $customer = User::factory()->create();

    Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PENDING,
        'subtotal' => '10.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '1.00',
        'total_amount' => '10.00',
        'currency' => 'GBP',
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'expires_at' => now()->addMinutes(15),
    ]);

    test()->withToken(myOrdersCustomerToken($customer))
        ->getJson('/api/public/my-orders-list')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});
