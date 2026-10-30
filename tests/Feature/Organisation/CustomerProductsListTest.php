<?php

use App\Enum\EventStatusEnum;
use App\Enum\EventTicketOfferStatusEnum;
use App\Enum\EventTicketStatusEnum;
use App\Enum\OrderItemPricingTypeEnum;
use App\Enum\OrderSalesChannelEnum;
use App\Enum\OrderStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Event;
use App\Models\EventTicket;
use App\Models\EventTicketOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
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
 *     offer: EventTicketOffer,
 *     token: string
 * }
 */
function customerProductsListFixture(): array
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
        'name' => 'The Summer Social',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $ticket = EventTicket::query()->create([
        'event_id' => $event->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'General Admission',
        'base_price' => 40.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $owner->id,
        'updated_by' => $owner->id,
    ]);

    $offer = EventTicketOffer::query()->create([
        'event_ticket_id' => $ticket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Early Birds',
        'price' => 35.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
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
        'offer' => $offer,
        'token' => $token,
    ];
}

test('customer products list requires authentication', function () {
    test()->getJson('/api/organisation/customer/1/products')->assertUnauthorized();
});

test('organisation can list products purchased by a linked customer', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'offer' => $offer,
        'token' => $token,
    ] = customerProductsListFixture();

    $customer = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $order = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
        'subtotal' => '140.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '14.00',
        'total_amount' => '140.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);

    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $offer->id,
        'ticket_name' => 'General Admission',
        'offer_name' => 'Early Birds',
        'quantity' => 4,
        'pricing_type' => OrderItemPricingTypeEnum::PAID,
        'unit_price' => '35.00',
        'discount_amount' => '0.00',
        'total_price' => '140.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '14.00',
        'organiser_share_amount' => '126.00',
        'currency' => 'GBP',
    ]);

    OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
        'first_order_id' => $order->id,
        'last_order_id' => $order->id,
        'status' => StatusEnum::ACTIVE,
    ]);

    // Another customer's product must not appear.
    $otherCustomer = User::factory()->create(['email' => 'other@example.com']);
    $otherOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $otherCustomer->id,
        'status' => OrderStatusEnum::PAID,
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
        'subtotal' => '35.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '3.50',
        'total_amount' => '35.00',
        'currency' => 'GBP',
        'customer_name' => 'Other Buyer',
        'customer_email' => $otherCustomer->email,
        'paid_at' => now(),
    ]);
    OrderItem::query()->create([
        'order_id' => $otherOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $offer->id,
        'ticket_name' => 'General Admission',
        'offer_name' => 'Early Birds',
        'quantity' => 1,
        'pricing_type' => OrderItemPricingTypeEnum::PAID,
        'unit_price' => '35.00',
        'discount_amount' => '0.00',
        'total_price' => '35.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '3.50',
        'organiser_share_amount' => '31.50',
        'currency' => 'GBP',
    ]);
    OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $otherCustomer->id,
        'first_order_id' => $otherOrder->id,
        'last_order_id' => $otherOrder->id,
        'status' => StatusEnum::ACTIVE,
    ]);

    test()->withToken($token)
        ->getJson('/api/organisation/customer/'.$customer->id.'/products')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('messages.organisation_customer_products_list'))
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $item->id)
        ->assertJsonPath('data.items.0.order_id', $order->id)
        ->assertJsonPath('data.items.0.order_unique_id', $order->unique_id)
        ->assertJsonPath('data.items.0.status', OrderStatusEnum::PAID->value)
        ->assertJsonPath('data.items.0.ticket_name', 'General Admission')
        ->assertJsonPath('data.items.0.offer_name', 'Early Birds')
        ->assertJsonPath('data.items.0.quantity', 4)
        ->assertJsonPath('data.items.0.total_price', '140.00')
        ->assertJsonPath('data.items.0.formatted_total_price', '£140.00')
        ->assertJsonPath('data.items.0.currency', 'GBP')
        ->assertJsonPath('data.items.0.pricing_type', OrderItemPricingTypeEnum::PAID->value)
        ->assertJsonPath('data.items.0.event_name', 'The Summer Social')
        ->assertJsonPath('data.items.0.sales_channel', OrderSalesChannelEnum::ONLINE->value)
        ->assertJsonPath('data.items.0.ticket_unique_id', $ticket->unique_id)
        ->assertJsonPath('data.items.0.offer_unique_id', $offer->unique_id)
        ->assertJsonStructure([
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'order_id',
                        'order_unique_id',
                        'status',
                        'ticket_name',
                        'offer_name',
                        'quantity',
                        'total_price',
                        'formatted_total_price',
                        'currency',
                        'pricing_type',
                        'event_name',
                        'sales_channel',
                        'paid_at',
                        'created_at',
                        'ticket_unique_id',
                        'offer_unique_id',
                        'issued_tickets',
                    ],
                ],
                'pagination' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'has_more',
                ],
            ],
        ]);
});

test('customer products list can search by ticket offer order event amount status and sales channel', function () {
    [
        'organisation' => $organisation,
        'event' => $event,
        'ticket' => $ticket,
        'offer' => $offer,
        'token' => $token,
    ] = customerProductsListFixture();

    $customer = User::factory()->create([
        'email' => 'ada@example.com',
    ]);

    $matchedOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'sales_channel' => OrderSalesChannelEnum::BOX_OFFICE,
        'subtotal' => '140.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '14.00',
        'total_amount' => '140.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);

    $matchedItem = OrderItem::query()->create([
        'order_id' => $matchedOrder->id,
        'event_id' => $event->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $ticket->id,
        'ticket_offer_id' => $offer->id,
        'ticket_name' => 'General Admission',
        'offer_name' => 'Early Birds',
        'quantity' => 4,
        'pricing_type' => OrderItemPricingTypeEnum::PAID,
        'unit_price' => '35.00',
        'discount_amount' => '0.00',
        'total_price' => '140.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '14.00',
        'organiser_share_amount' => '126.00',
        'currency' => 'GBP',
    ]);

    $otherEvent = Event::query()->create([
        'organisation_id' => $organisation->id,
        'unique_id' => UniqueIdGenerator::generate('EVT'),
        'name' => 'Winter Gala',
        'status' => EventStatusEnum::PUBLISHED,
        'published_at' => now(),
        'created_by' => $organisation->owner_id,
        'updated_by' => $organisation->owner_id,
    ]);
    $otherTicket = EventTicket::query()->create([
        'event_id' => $otherEvent->id,
        'unique_id' => UniqueIdGenerator::generate('TKT'),
        'name' => 'Day Pass',
        'base_price' => 20.00,
        'currency' => 'GBP',
        'quantity_cap' => 100,
        'status' => EventTicketStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $organisation->owner_id,
        'updated_by' => $organisation->owner_id,
    ]);
    $otherOffer = EventTicketOffer::query()->create([
        'event_ticket_id' => $otherTicket->id,
        'unique_id' => UniqueIdGenerator::generate('OFR'),
        'name' => 'Standard',
        'price' => 20.00,
        'quantity_cap' => 50,
        'access' => 'public',
        'status' => EventTicketOfferStatusEnum::ACTIVE,
        'sort_order' => 1,
        'created_by' => $organisation->owner_id,
        'updated_by' => $organisation->owner_id,
    ]);

    $otherOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::REFUNDED,
        'sales_channel' => OrderSalesChannelEnum::ONLINE,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);

    OrderItem::query()->create([
        'order_id' => $otherOrder->id,
        'event_id' => $otherEvent->id,
        'organisation_id' => $organisation->id,
        'event_ticket_id' => $otherTicket->id,
        'ticket_offer_id' => $otherOffer->id,
        'ticket_name' => 'Day Pass',
        'offer_name' => 'Standard',
        'quantity' => 1,
        'pricing_type' => OrderItemPricingTypeEnum::PAID,
        'unit_price' => '20.00',
        'discount_amount' => '0.00',
        'total_price' => '20.00',
        'platform_fee_rate' => '10.00',
        'platform_share_amount' => '2.00',
        'organiser_share_amount' => '18.00',
        'currency' => 'GBP',
    ]);

    OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
        'first_order_id' => $matchedOrder->id,
        'last_order_id' => $otherOrder->id,
        'status' => StatusEnum::ACTIVE,
    ]);

    $assertMatched = function (string $search) use ($token, $customer, $matchedItem): void {
        test()->withToken($token)
            ->getJson('/api/organisation/customer/'.$customer->id.'/products?search='.urlencode($search))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $matchedItem->id);
    };

    $assertMatched($ticket->unique_id);
    $assertMatched($offer->unique_id);
    $assertMatched('General Admission');
    $assertMatched('Early Birds');
    $assertMatched($matchedOrder->unique_id);
    $assertMatched('140.00');
    $assertMatched(OrderStatusEnum::PAID->value);
    $assertMatched('The Summer Social');
    $assertMatched(OrderSalesChannelEnum::BOX_OFFICE->value);
});

test('customer products list returns not found for unknown customer', function () {
    ['token' => $token] = customerProductsListFixture();

    test()->withToken($token)
        ->getJson('/api/organisation/customer/999999/products')
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.organisation_customer_not_found'));
});
