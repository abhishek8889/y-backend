<?php

namespace Database\Factories;

use App\Enum\StatusEnum;
use App\Models\Organisation;
use App\Models\OrganisationCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationCustomer>
 */
class OrganisationCustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'first_order_id' => null,
            'last_order_id' => null,
            'status' => StatusEnum::ACTIVE,
            'status_updated_at' => null,
            'status_updated_reason' => null,
        ];
    }

    public function inactive(?string $reason = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusEnum::INACTIVE,
            'status_updated_at' => now(),
            'status_updated_reason' => $reason,
        ]);
    }
}
