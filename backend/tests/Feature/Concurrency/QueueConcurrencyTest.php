<?php

namespace Tests\Feature\Concurrency;

use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Real concurrency (docs/CONCURRENCY_STRATEGY.md): many separate PHP
 * processes, each with its own database connection, hit the same queue at
 * the same instant (a shared start time). Only the database's row locks and
 * unique indexes stand between them, as with concurrent HTTP requests.
 *
 * Runs on MySQL and MariaDB only. SQLite serializes every write for the
 * whole file, so it cannot show that the locking strategy works; these
 * tests are skipped there rather than pretending.
 */
class QueueConcurrencyTest extends TestCase
{
    use BuildsQueues, BuildsTenants, DatabaseTruncation;

    private const WORKERS = 16;

    private User $owner;

    private Queue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need MySQL or MariaDB (SQLite serializes all writes).');
        }

        $this->owner = User::factory()->create();
        $business = $this->businessOwnedBy($this->owner);
        $this->queue = $this->queueFor($this->serviceOf($this->branchOf($business)), [
            'ticket_prefix' => 'C',
            'grace_period_seconds' => 0,
        ]);
    }

    /**
     * Runs the commands in parallel, released together, and returns each
     * worker's decoded JSON result in command order.
     *
     * @param  list<list<string>>  $commands  Action and arguments per worker.
     * @return list<array<string, mixed>>
     */
    private function race(array $commands): array
    {
        $php = (new PhpExecutableFinder)->find(false) ?: PHP_BINARY;
        $worker = base_path('tests/Concurrency/worker.php');
        // Leaves time for every process to boot Laravel and connect.
        $startAt = sprintf('%.6f', microtime(true) + 2.5 + count($commands) * 0.15);

        $processes = array_map(function (array $command) use ($php, $worker, $startAt): Process {
            $process = new Process([$php, $worker, $startAt, ...$command], base_path(), timeout: 120);
            $process->start();

            return $process;
        }, $commands);

        return array_map(function (Process $process): array {
            $process->wait();
            $lines = array_values(array_filter(explode(PHP_EOL, trim($process->getOutput()))));
            $result = json_decode((string) end($lines), true);

            $this->assertIsArray($result, 'Worker output: '.$process->getOutput().$process->getErrorOutput());
            $this->assertNotSame('error', $result['code'] ?? null, (string) ($result['error'] ?? ''));

            return $result;
        }, $processes);
    }

    private function openQueue(): QueueSession
    {
        return $this->openSession($this->queue, $this->owner);
    }

    /**
     * Leaves the database empty for the transaction-based tests that follow.
     */
    protected function tearDown(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->truncateTablesForAllConnections();
        }

        parent::tearDown();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    private function succeeded(array $results): array
    {
        return array_values(array_filter($results, fn (array $result) => $result['ok']));
    }

    /**
     * Proves the harness creates real overlap: the same workers running a
     * naive read-then-write (the "MAX() + 1" pattern the engine avoids) lose
     * updates. Without this, the tests below could pass merely because the
     * processes happened to run one after another.
     */
    public function test_the_harness_really_runs_workers_at_the_same_time(): void
    {
        $id = DB::table('queue_daily_counters')->insertGetId([
            'queue_id' => $this->queue->getKey(),
            'business_date' => '2000-01-01',
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $results = $this->race(array_fill(0, self::WORKERS, ['unsafe-increment', (string) $id]));

        $this->assertCount(self::WORKERS, $this->succeeded($results));
        $final = (int) DB::table('queue_daily_counters')->where('id', $id)->value('last_number');
        $this->assertLessThan(self::WORKERS, $final, 'Overlapping workers lost updates, as an unsafe counter must.');
        $this->assertLessThan(
            self::WORKERS,
            count(array_unique(array_column($results, 'read'))),
            'Several workers read the same value before any of them wrote.',
        );
    }

    public function test_simultaneous_joins_get_unique_consecutive_numbers(): void
    {
        $this->openQueue();
        $customers = User::factory()->count(self::WORKERS)->create();

        $results = $this->race($customers->map(fn (User $user) => [
            'join', (string) $this->queue->getKey(), (string) $user->getKey(),
        ])->all());

        $numbers = array_column($this->succeeded($results), 'number');
        sort($numbers);
        $this->assertSame(range(1, self::WORKERS), $numbers, 'Every join succeeded with a distinct, gap-free number.');
        $this->assertSame(self::WORKERS, QueueTicket::query()->distinct()->count('ticket_number'));
        $this->assertSame(self::WORKERS, (int) DB::table('queue_daily_counters')->value('last_number'));
    }

    public function test_one_customer_racing_themselves_gets_one_ticket(): void
    {
        $this->openQueue();
        $customer = User::factory()->create();

        $results = $this->race(array_fill(0, 8, ['join', (string) $this->queue->getKey(), (string) $customer->getKey()]));

        $this->assertCount(1, $this->succeeded($results));
        $this->assertSame(
            array_fill(0, 7, 'duplicate_active_ticket'),
            array_column(array_filter($results, fn ($r) => ! $r['ok']), 'code'),
        );
        $this->assertSame(1, QueueTicket::query()->count());
    }

    public function test_retries_with_one_idempotency_key_create_one_ticket(): void
    {
        $this->openQueue();
        $customer = User::factory()->create();
        $key = 'concurrent-retry-key-0001';

        $results = $this->race(array_fill(0, 8, ['join', (string) $this->queue->getKey(), (string) $customer->getKey(), $key]));

        $this->assertCount(8, $this->succeeded($results), 'Every retry gets the ticket.');
        $this->assertCount(1, array_unique(array_column($results, 'ticket')));
        $this->assertCount(1, array_filter(array_column($results, 'created')));
        $this->assertSame(1, QueueTicket::query()->count());
    }

    public function test_capacity_holds_under_simultaneous_guest_joins(): void
    {
        $this->queue->update(['capacity' => 5]);
        $this->openQueue();

        $results = $this->race(array_fill(0, self::WORKERS, ['guest', (string) $this->queue->getKey()]));

        $this->assertCount(5, $this->succeeded($results));
        $this->assertCount(self::WORKERS - 5, array_filter($results, fn ($r) => ($r['code'] ?? null) === 'queue_full'));
        $this->assertSame(5, QueueTicket::query()->where('status', TicketStatus::Waiting->value)->count());
    }

    public function test_simultaneous_call_next_never_gives_two_staff_the_same_customer(): void
    {
        $session = $this->openQueue();
        foreach (range(1, 10) as $i) {
            $this->ticketIn($session);
        }
        $business = $this->queue->branch->business;
        $staff = collect(range(1, 6))->map(function () use ($business): User {
            $employee = $this->memberOf($business, BusinessRole::Employee);
            $this->assignToQueue($this->queue, $employee);

            return $employee;
        });

        $results = $this->race($staff->map(fn (User $user) => [
            'call-next', (string) $session->getKey(), (string) $user->getKey(),
        ])->all());

        $called = array_column($this->succeeded($results), 'number');
        $this->assertCount(6, $called);
        $this->assertCount(6, array_unique($called), 'No ticket was called twice.');
        sort($called);
        $this->assertSame(range(1, 6), $called, 'The first six in line were called (FIFO).');
        $this->assertSame(6, QueueTicket::query()->where('status', TicketStatus::Called->value)->distinct()->count('served_by'));
        $this->assertSame(4, QueueTicket::query()->where('status', TicketStatus::Waiting->value)->count());
    }

    public function test_one_staff_member_calling_repeatedly_gets_one_customer(): void
    {
        $session = $this->openQueue();
        foreach (range(1, 5) as $i) {
            $this->ticketIn($session);
        }

        $results = $this->race(array_fill(0, 6, ['call-next', (string) $session->getKey(), (string) $this->owner->getKey()]));

        $this->assertCount(1, $this->succeeded($results));
        $this->assertCount(5, array_filter($results, fn ($r) => ($r['code'] ?? null) === 'staff_busy'));
        $this->assertSame(1, QueueTicket::query()->where('status', TicketStatus::Called->value)->count());
    }

    public function test_simultaneous_opens_create_one_session(): void
    {
        $results = $this->race(array_fill(0, 6, ['open', (string) $this->queue->getKey(), (string) $this->owner->getKey()]));

        $this->assertCount(1, $this->succeeded($results));
        $this->assertCount(5, array_filter($results, fn ($r) => ($r['code'] ?? null) === 'session_already_active'));
        $this->assertSame(1, QueueSession::query()->count());
    }
}
