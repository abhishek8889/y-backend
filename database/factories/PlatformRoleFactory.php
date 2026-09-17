<?php

namespace Database\Factories;

use App\Models\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformRole>
 */
class PlatformRoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
        ];
    }
}
