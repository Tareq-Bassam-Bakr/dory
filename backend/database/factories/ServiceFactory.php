<?php

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Models\Branch;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => fake()->optional()->sentence(),
            'estimated_duration_minutes' => fake()->randomElement([5, 10, 15, 20, 30, 45, 60]),
            'display_order' => fake()->numberBetween(0, 100),
            'status' => ServiceStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ServiceStatus::Inactive]);
    }
}
