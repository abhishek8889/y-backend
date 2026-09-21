<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_id' => User::factory(),
            'unique_id' => (string) Str::ulid(),
            'organiser_name' => fake()->name(),
            'name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'country_code' => '+44',
            'phone' => fake()->numerify('7#########'),
            'country' => 'GB',
            'complete_status' => false,
            'approve_status' => false,
        ];
    }
}
