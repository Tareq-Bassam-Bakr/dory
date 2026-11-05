<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Random per process: there is no hardcoded password anywhere. Tests read
     * it through plainPassword().
     */
    protected static ?string $plainPassword = null;

    protected static ?string $hashedPassword = null;

    public static function plainPassword(): string
    {
        return static::$plainPassword ??= Str::password(20);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password' => static::$hashedPassword ??= Hash::make(static::plainPassword()),
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => UserStatus::Active,
            'is_platform_admin' => false,
            'email_verified_at' => now(),
            'last_login_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_platform_admin' => true,
        ]);
    }
}
