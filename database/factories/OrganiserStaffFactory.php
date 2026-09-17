<?php

namespace Database\Factories;

use App\Enum\StatusEnum;
use App\Models\Organisation;
use App\Models\OrganiserStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganiserStaff>
 */
class OrganiserStaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'user_id' => User::factory(),
            'status' => StatusEnum::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StatusEnum::INACTIVE,
        ]);
    }
}
