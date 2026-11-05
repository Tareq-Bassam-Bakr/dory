<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates a bare business row. Use BusinessMembershipService::createBusinessWithOwner()
 * (or the BusinessMember factory) when the test needs members.
 *
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            // Opt in with categorized(): make() must not touch the database.
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->optional()->sentence(),
            'timezone' => 'UTC',
            'status' => BusinessStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function categorized(?BusinessCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category ?? BusinessCategory::factory(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::Suspended,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BusinessStatus::Inactive,
        ]);
    }
}
