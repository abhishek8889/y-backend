<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderStatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Support\Str;

/**
 * @return array{
 *     owner: User,
 *     organisation: Organisation,
 *     event: Event,
 *     ticket: EventTicket,
 *     token: string
 * }
 */
function customerInfoOrgFixture(): array
{
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $owner->id,
        'approve_status' => true,
    ]);
    OrganiserStaff::factory()->for($organisation)->for($owner)->create();

    $event = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Customer Info Event',
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

    $token = test()->postJson('/api/organisation/login', [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('data.access_token');

    return [
        'owner' => $owner,
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ];
}

/**
 * @return array{order: Order, customer: User}
 */
function createPaidOrderForCustomer(
    Organisation $organisation,
    Event $event,
    EventTicket $ticket,
    User $customer,
    string $totalAmount = '20.00',
    int $quantity = 1,
): array {
    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => $totalAmount,
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => $totalAmount,
        'currency' => 'GBP',
        'customer_name' => trim($customer->first_name.' '.$customer->last_name),
        'customer_email' => $customer->email,
        'customer_phone' => $customer->phone,
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_name' => $ticket->name,
        'offer_name' => null,
        'quantity' => $quantity,
        'unit_price' => $totalAmount,
        'discount_amount' => '0.00',
        'total_price' => $totalAmount,
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    return [
        'order' => $order,
        'customer' => $customer,
    ];
}

test('customer info requires authentication', function () {
    test()->getJson('/api/organisation/customer/info/1')->assertUnauthorized();
});

test('organisation can view customer details for a buyer with orders', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'token' => $token,
    ] = customerInfoOrgFixture();

    $customer = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'phone' => '07111111111',
        'country_code' => '+44',
        'country' => 'GB',
    ]);

    createPaidOrderForCustomer($organisation, $event, $ticket, $customer, '20.00', 2);
    createPaidOrderForCustomer($organisation, $event, $ticket, $customer, '15.00', 1);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/info/'.$customer->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_customer_details'))
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.first_name', 'Ada')
        ->assertJsonPath('data.last_name', 'Lovelace')
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.phone', '07111111111')
        ->assertJsonPath('data.country_code', '+44')
        ->assertJsonPath('data.country', 'GB')
        ->assertJsonPath('data.summary.orders_count', 2)
        ->assertJsonPath('data.summary.paid_orders_count', 2)
        ->assertJsonPath('data.summary.tickets_count', 3)
        ->assertJsonPath('data.summary.total_spent', '35.00')
        ->assertJsonPath('data.summary.currency', 'GBP')
        ->assertJsonPath('data.summary.formatted_total_spent', '£35.00')
        ->assertJsonStructure([
            'data' => [
                'id',
                'first_name',
                'last_name',
                'email',
                'phone',
                'country_code',
                'country',
                'profile_image',
                'status',
                'email_verified_at',
                'created_at',
                'summary' => [
                    'orders_count',
                    'paid_orders_count',
                    'tickets_count',
                    'total_spent',
                    'formatted_total_spent',
                    'currency',
                    'last_order_at',
                ],
            ],
        ]);
});

test('customer info returns not found for unknown customer', function () {
    ['token' => $token] = customerInfoOrgFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/customer/info/999999')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_customer_not_found'));
});

test('customer info returns not found for buyer of another organisation', function () {
    [
        'token' => $token,
    ] = customerInfoOrgFixture();

    $otherOwner = User::factory()->create();
    $otherOrganisation = Organisation::factory()->create([
        'owner_id' => $otherOwner->id,
        'approve_status' => true,
    ]);

    $otherEvent = Event::query()->create([
        'organisation_id' => $otherOrganisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Other Org Event',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    $otherTicket = EventTicket::query()->create([
        'event_id' => $otherEvent->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'Other Ticket',
        'base_price' => 10.00,
        'currency' => 'GBP',
        'quantity_cap' => 50,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
    ]);

    $customer = User::factory()->create([
        'email' => 'other-buyer@example.com',
    ]);

    createPaidOrderForCustomer($otherOrganisation, $otherEvent, $otherTicket, $customer);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/info/'.$customer->id)
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_customer_not_found'));
});
