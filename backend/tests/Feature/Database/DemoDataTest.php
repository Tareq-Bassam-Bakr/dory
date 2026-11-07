<?php

namespace Tests\Feature\Database;

use App\Models\Business;
use App\Models\Queue;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo records must stay out of production, and must really work elsewhere.
 */
class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_never_gets_demo_records(): void
    {
        config(['dory.demo.password' => 'Demo-Password-1']);
        $this->app['env'] = 'production';

        // --force: seeding in production otherwise asks for confirmation.
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, Business::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_without_a_demo_password_nothing_is_created(): void
    {
        config(['dory.demo.password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Business::query()->count());
    }

    public function test_the_demo_business_can_be_found_and_joined(): void
    {
        config(['dory.demo.password' => 'Demo-Password-1']);

        $this->seed(DatabaseSeeder::class);

        $this->getJson('/api/v1/discovery/businesses?q=demo')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'dory-demo-clinic')
            ->assertJsonPath('data.0.accepting_queues_count', 1);

        $queue = Queue::query()->sole();
        $this->postJson('/api/v1/public/queues/'.$queue->public_id.'/join', ['name' => 'Rami'])
            ->assertCreated()
            ->assertJsonPath('data.ticket_number', 'DEMO-001');
    }

    public function test_seeding_twice_changes_nothing(): void
    {
        config(['dory.demo.password' => 'Demo-Password-1']);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Business::query()->count());
        $this->assertSame(1, Queue::query()->count());
    }
}
