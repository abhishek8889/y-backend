<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'organisation_id' => Organisation::factory(),
            'name' => $name,
            'slug' => Role::slugFromName($name),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
