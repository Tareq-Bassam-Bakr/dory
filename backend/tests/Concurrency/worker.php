<?php

/*
 * One competing client for QueueConcurrencyTest. Each worker is a separate
 * PHP process with its own database connection, so the database itself
 * arbitrates (row locks, unique indexes), exactly as with concurrent HTTP
 * requests.
 *
 * Usage: php worker.php <start-at-unix-time> <action> <args...>
 *   join      <queue id> <user id> [idempotency key]
 *   guest     <queue id>
 *   call-next <session id> <staff user id>
 *   open      <queue id> <user id>
 *   unsafe-increment <counter row id>   (harness self-check)
 *
 * Prints one JSON line: {"ok":true,...} or {"ok":false,"code":"..."}.
 */

use App\Exceptions\QueueException;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\User;
use App\Services\Queues\QueueOperationsService;
use App\Services\Queues\QueueSessionService;
use App\Services\Queues\TicketJoinService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $action] = $argv;
$args = array_slice($argv, 3);

$queue = fn (string $id): Queue => Queue::query()->with(['branch', 'service'])->findOrFail($id);

// Connect and warm up before the barrier, so every worker starts its
// transaction at (nearly) the same moment.
DB::connection()->getPdo();
while (microtime(true) < (float) $startAt) {
    usleep(200);
}

try {
    $result = match ($action) {
        'join' => (function () use ($queue, $args): array {
            $join = app(TicketJoinService::class)->joinAsCustomer(
                $queue($args[0]),
                User::query()->findOrFail($args[1]),
                $args[2] ?? null,
            );

            return ['ticket' => $join->ticket->public_id, 'number' => $join->ticket->sequence_number, 'created' => $join->created];
        })(),
        'guest' => (function () use ($queue, $args): array {
            $join = app(TicketJoinService::class)->joinAsGuest($queue($args[0]), 'Guest', null);

            return ['ticket' => $join->ticket->public_id, 'number' => $join->ticket->sequence_number, 'created' => $join->created];
        })(),
        'call-next' => (function () use ($args): array {
            $session = QueueSession::query()->with(['queue.branch', 'queue.service'])->findOrFail($args[0]);
            $ticket = app(QueueOperationsService::class)->callNext($session, User::query()->findOrFail($args[1]));

            return ['ticket' => $ticket->public_id, 'number' => $ticket->sequence_number];
        })(),
        'open' => (function () use ($queue, $args): array {
            $session = app(QueueSessionService::class)->open($queue($args[0]), User::query()->findOrFail($args[1]));

            return ['session' => $session->public_id];
        })(),
        // Harness check only: a deliberately unsafe read-then-write. If the
        // workers really overlap, some increments are lost.
        'unsafe-increment' => (function () use ($args): array {
            $row = DB::table('queue_daily_counters')->where('id', (int) $args[0]);
            $value = (int) $row->value('last_number');
            usleep(50_000);
            $row->update(['last_number' => $value + 1]);

            return ['read' => $value];
        })(),
        default => throw new InvalidArgumentException('Unknown action '.$action),
    };
    echo json_encode(['ok' => true] + $result), PHP_EOL;
} catch (QueueException $e) {
    echo json_encode(['ok' => false, 'code' => $e->errorCode()]), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'code' => 'error', 'error' => $e::class.': '.$e->getMessage()]), PHP_EOL;
}
