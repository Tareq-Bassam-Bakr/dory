<?php

namespace Database\Factories;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => fake()->city().' Branch',
            'address' => fake()->streetAddress(),
            'country' => 'SY',
            'city' => fake()->city(),
            'region' => null,
            'latitude' => fake()->latitude(32, 37),
            'longitude' => fake()->longitude(35, 42),
            'phone' => null,
            'email' => null,
            'timezone' => 'Asia/Damascus',
            'status' => BranchStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => BranchStatus::Inactive]);
    }
}
