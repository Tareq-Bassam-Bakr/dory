<?php

namespace Database\Factories;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\MembershipStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessMember>
 */
class BusinessMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'role' => BusinessRole::Employee,
            'status' => MembershipStatus::Active,
            'branch_access' => BranchAccess::All,
            'joined_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => BusinessRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => BusinessRole::Manager]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => MembershipStatus::Suspended]);
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::Invited,
            'joined_at' => null,
        ]);
    }

    /**
     * Limits the membership to branches attached afterwards.
     */
    public function selectedBranches(): static
    {
        return $this->state(fn (array $attributes) => ['branch_access' => BranchAccess::Selected]);
    }
}
