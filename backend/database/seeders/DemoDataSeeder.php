<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Database\Seeder;

/**
 * Local demo data: one business with an owner and an employee, plus a
 * customer-only account. Idempotent, so it can be re-run safely.
 *
 * The password is read from SEED_DEMO_PASSWORD; nothing is seeded without it,
 * so no password is ever hardcoded in the repository.
 */
class DemoDataSeeder extends Seeder
{
    public function __construct(private readonly BusinessMembershipService $memberships) {}

    public function run(): void
    {
        $password = config('dory.demo.password');

        if (blank($password)) {
            $this->command?->warn('SEED_DEMO_PASSWORD is not set: demo accounts were not created.');

            return;
        }

        $owner = $this->demoUser('owner@dory.test', 'Demo Owner', $password);
        $employee = $this->demoUser('employee@dory.test', 'Demo Employee', $password);
        $this->demoUser('customer@dory.test', 'Demo Customer', $password);

        $business = Business::query()->where('slug', 'dory-demo-clinic')->first()
            ?? $this->memberships->createBusinessWithOwner($owner, 'DORY Demo Clinic', slug: 'dory-demo-clinic');

        if ($this->memberships->findMembership($employee, $business, activeOnly: false) === null) {
            $this->memberships->addMember($business, $employee, BusinessRole::Employee);
        }

        $this->command?->info('Demo data ready: owner@dory.test, employee@dory.test, customer@dory.test.');
    }

    private function demoUser(string $email, string $name, string $password): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );
    }
}
