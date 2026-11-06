<?php

namespace Database\Factories;

use App\Enums\QueueStatus;
use App\Models\Branch;
use App\Models\Queue;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Queues for tests and demo data. The service always belongs to the queue's
 * branch, as the API guarantees.
 *
 * @extends Factory<Queue>
 */
class QueueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'service_id' => fn (array $attributes) => Service::factory()->state(['branch_id' => $attributes['branch_id']]),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'ticket_prefix' => fake()->unique()->regexify('[A-Z]{3}'),
            'status' => QueueStatus::Active,
            'capacity' => null,
            'allow_guest_join' => true,
            'require_guest_name' => true,
            'require_guest_phone' => false,
            'estimated_service_duration' => 10,
            'grace_period_seconds' => 120,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Queue $queue): void {
            if ($queue->public_token_hash === null) {
                $token = Str::random(40);
                $queue->public_token_hash = hash('sha256', $token);
                $queue->public_token_encrypted = $token;
                $queue->public_token_rotated_at = now();
            }
        });
    }

    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $service->branch_id,
            'service_id' => $service->getKey(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QueueStatus::Inactive]);
    }
}
