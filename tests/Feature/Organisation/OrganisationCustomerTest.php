<?php

use App\Enum\OrderStatusEnum;
use App\Enum\StatusEnum;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
use App\Models\User;
use App\Support\UniqueIdGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

test('organisation customer belongs to organisation user and orders', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->create([
        'owner_id' => $user->id,
    ]);
    $customer = User::factory()->create();

    $firstOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '20.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '2.00',
        'total_amount' => '20.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);

    $lastOrder = Order::query()->create([
        'unique_id' => UniqueIdGenerator::generate('ORD', 12),
        'checkout_key' => (string) Str::uuid(),
        'user_id' => $customer->id,
        'status' => OrderStatusEnum::PAID,
        'subtotal' => '10.00',
        'discount_amount' => '0.00',
        'platform_fee_amount' => '1.00',
        'total_amount' => '10.00',
        'currency' => 'GBP',
        'customer_name' => 'Ada Lovelace',
        'customer_email' => $customer->email,
        'paid_at' => now(),
    ]);

    $membership = OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
        'first_order_id' => $firstOrder->id,
        'last_order_id' => $lastOrder->id,
        'status' => StatusEnum::ACTIVE,
    ]);

    expect($membership->organisation->is($organisation))->toBeTrue()
        ->and($membership->user->is($customer))->toBeTrue()
        ->and($membership->firstOrder->is($firstOrder))->toBeTrue()
        ->and($membership->lastOrder->is($lastOrder))->toBeTrue()
        ->and($membership->status)->toBe(StatusEnum::ACTIVE)
        ->and($organisation->customers()->whereKey($membership->id)->exists())->toBeTrue()
        ->and($customer->organisationCustomers()->whereKey($membership->id)->exists())->toBeTrue()
        ->and($firstOrder->firstOrganisationCustomers()->whereKey($membership->id)->exists())->toBeTrue()
        ->and($lastOrder->lastOrganisationCustomers()->whereKey($membership->id)->exists())->toBeTrue();
});

test('organisation customer is unique per organisation and user', function () {
    $organisation = Organisation::factory()->create();
    $customer = User::factory()->create();

    OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
    ]);

    OrganisationCustomer::factory()->create([
        'organisation_id' => $organisation->id,
        'user_id' => $customer->id,
    ]);
})->throws(QueryException::class);
