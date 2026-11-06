<?php

namespace App\Services\Queues;

use App\Models\Queue;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues ticket numbers: 1, 2, 3 … per queue and branch-local date
 * (docs/TICKET_NUMBERING.md).
 *
 * MUST run inside the joining transaction. One statement inserts the
 * day's counter row or increments it:
 *
 *   INSERT … VALUES (queue, date, 1)
 *   ON DUPLICATE KEY UPDATE last_number = last_number + 1   (MySQL/MariaDB)
 *   ON CONFLICT (queue_id, business_date) DO UPDATE …        (SQLite)
 *
 * The statement takes an exclusive row lock on the counter, held until the
 * transaction commits, so concurrent joins of the same queue and day are
 * serialized and each reads its own value. Numbers are never decremented:
 * a cancelled or rolled-back ticket's number is not handed out again
 * (a rollback also rolls back the increment, so no gap appears then).
 * The unique (queue_id, business_date, sequence_number) index on tickets
 * backs this up at the database level.
 */
final class TicketNumberAllocator
{
    public function next(Queue $queue, CarbonInterface $businessDate): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Ticket numbers must be allocated inside a transaction.');
        }

        $date = $businessDate->toDateString();
        $now = now();

        DB::table('queue_daily_counters')->upsert(
            [[
                'queue_id' => $queue->getKey(),
                'business_date' => $date,
                'last_number' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['queue_id', 'business_date'],
            ['last_number' => DB::raw('last_number + 1'), 'updated_at' => $now],
        );

        return (int) DB::table('queue_daily_counters')
            ->where('queue_id', $queue->getKey())
            ->where('business_date', $date)
            ->lockForUpdate()
            ->value('last_number');
    }

    /**
     * "BAR" + 7 → "BAR-007". At least three digits; more when needed.
     */
    public static function format(string $prefix, int $number): string
    {
        return $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }
}
