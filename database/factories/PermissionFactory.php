<?php

namespace Database\Factories;

use App\Enum\PermissionEnum;
use App\Enum\PermissionScopeEnum;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => 'custom',
            'name' => fake()->unique()->bothify('custom.###??'),
            'scope' => PermissionScopeEnum::ORGANISATION,
            'description' => fake()->sentence(),
        ];
    }

    public function named(PermissionEnum $permission): static
    {
        return $this->state(fn (array $attributes): array => [
            'module' => $permission->module(),
            'name' => $permission->value,
            'scope' => $permission->scope(),
            'description' => $permission->description(),
        ]);
    }

    public function platform(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => PermissionScopeEnum::PLATFORM,
        ]);
    }

    public function organisation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => PermissionScopeEnum::ORGANISATION,
        ]);
    }
}
