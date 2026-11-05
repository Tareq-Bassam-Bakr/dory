<?php

namespace Database\Factories;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Models\Business;
use App\Models\BusinessInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A pending invitation with a random token. Use withToken() when the test
 * needs to know the plain token.
 *
 * @extends Factory<BusinessInvitation>
 */
class BusinessInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => BusinessRole::Employee,
            'branch_access' => BranchAccess::All,
            'branch_ids' => null,
            'token_hash' => BusinessInvitation::hashToken(Str::random(64)),
            'status' => InvitationStatus::Pending,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (BusinessInvitation $invitation): void {
            if ($invitation->status === InvitationStatus::Pending && $invitation->pending_key === null) {
                $invitation->pending_key = BusinessInvitation::pendingKeyFor($invitation->business, $invitation->email);
            }
        });
    }

    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => ['token_hash' => BusinessInvitation::hashToken($token)]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
            'pending_key' => null,
            'responded_at' => now(),
        ]);
    }
}
