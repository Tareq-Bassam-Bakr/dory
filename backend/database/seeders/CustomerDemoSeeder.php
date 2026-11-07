<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\Queue;
use App\Models\Service;
use App\Models\User;
use App\Services\Queues\QueueService;
use App\Services\Queues\QueueSessionService;
use Illuminate\Database\Seeder;

/**
 * Makes the demo business visible on the customer side: a branch with
 * coordinates, and a service with an open queue that accepts guests.
 *
 * Local demo data only: DatabaseSeeder never runs this in production, and it
 * is named clearly as a demo. Idempotent, and running it again the next day
 * reopens the queue.
 */
class CustomerDemoSeeder extends Seeder
{
    public const BUSINESS_SLUG = 'dory-demo-clinic';

    public function __construct(
        private readonly QueueService $queues,
        private readonly QueueSessionService $sessions,
    ) {}

    public function run(): void
    {
        $business = Business::query()->where('slug', self::BUSINESS_SLUG)->first();
        $owner = User::query()->where('email', 'owner@dory.test')->first();

        if ($business === null || $owner === null) {
            return;
        }

        $business->category_id ??= BusinessCategory::query()->where('slug', 'healthcare-clinics')->value('id');
        $business->description ??= 'A demo business for trying DORY locally.';
        $business->save();

        $branch = $this->branch($business);
        $service = $this->service($branch);
        $queue = $this->queue($branch, $service)->load('branch');

        if (! $queue->activeSession()->exists()) {
            $this->sessions->open($queue, $owner);
        }

        $this->command?->info('Customer demo ready: DORY Demo Clinic has an open queue in Damascus.');
    }

    private function branch(Business $business): Branch
    {
        return $business->branches()->first() ?? $business->branches()->create([
            'name' => 'Demo branch',
            'address' => 'Demo street 1',
            'city' => 'Damascus',
            'country' => 'SY',
            'latitude' => 33.5138,
            'longitude' => 36.2765,
            'timezone' => 'Asia/Damascus',
        ]);
    }

    private function service(Branch $branch): Service
    {
        return $branch->services()->first() ?? $branch->services()->create([
            'name' => 'Demo consultation',
            'estimated_duration_minutes' => 10,
        ]);
    }

    private function queue(Branch $branch, Service $service): Queue
    {
        return $service->queues()->first() ?? $this->queues->create($branch, $service, [
            'name' => 'Demo queue',
            'ticket_prefix' => 'DEMO',
        ]);
    }
}
