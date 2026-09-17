<?php

namespace Database\Factories;

use App\Enum\StatusEnum;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformStaff>
 */
class PlatformStaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
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
