<?php

namespace Tests\Feature\Database;

use App\Enums\BusinessRole;
use App\Enums\InvitationStatus;
use App\Enums\TicketStatus;
use App\Models\BusinessInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * MariaDB (explicit_defaults_for_timestamp off) silently adds
 * ON UPDATE CURRENT_TIMESTAMP to the first NOT NULL TIMESTAMP column of a
 * table. MySQL 8.4 does not. A join time or an expiry date must never change
 * because something else in the row did. Meaningful on MariaDB; passes
 * trivially on SQLite and MySQL.
 */
class TimestampColumnsTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsTenants, RefreshDatabase;

    public function test_no_column_changes_itself_on_update(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('SQLite has no ON UPDATE clause.');
        }

        $columns = collect(DB::select(
            "select table_name as t, column_name as c from information_schema.columns
             where table_schema = database() and extra like '%on update%'",
        ))->map(fn (object $column): string => $column->t.'.'.$column->c)->all();

        $this->assertSame([], $columns);
    }

    public function test_a_tickets_join_time_survives_its_status_changes(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [, , $session] = $this->openQueueIn($branch);
        $joinedAt = CarbonImmutable::parse('2026-01-02 03:04:05', 'UTC');
        $ticket = $this->ticketIn($session, TicketStatus::Waiting, ['joined_at' => $joinedAt]);

        $ticket->forceFill(['status' => TicketStatus::Cancelled])->save();

        $this->assertSame($joinedAt->toIso8601String(), $ticket->fresh()->joined_at->utc()->toIso8601String());
    }

    public function test_a_sessions_opening_time_survives_pausing(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [, , $session] = $this->openQueueIn($branch);
        $openedAt = CarbonImmutable::parse('2026-01-02 03:04:05', 'UTC');
        $session->forceFill(['opened_at' => $openedAt])->save();

        $session->forceFill(['status' => 'paused', 'paused_at' => now()])->save();

        $this->assertSame($openedAt->toIso8601String(), $session->fresh()->opened_at->utc()->toIso8601String());
    }

    public function test_a_staff_assignment_keeps_its_date_when_it_ends(): void
    {
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner);
        $queue = $this->queueFor($this->serviceOf($this->branchOf($business)));
        $staff = $this->memberOf($business, BusinessRole::Employee);
        $assignment = $this->assignToQueue($queue, $staff);
        $assignedAt = CarbonImmutable::parse('2026-01-02 03:04:05', 'UTC');
        $assignment->forceFill(['assigned_at' => $assignedAt])->save();

        $assignment->forceFill(['unassigned_at' => now()])->save();

        $this->assertSame($assignedAt->toIso8601String(), $assignment->fresh()->assigned_at->utc()->toIso8601String());
    }

    public function test_an_invitation_keeps_its_expiry_when_it_changes(): void
    {
        $invitation = BusinessInvitation::factory()->create();
        $expiresAt = CarbonImmutable::parse('2030-01-02 03:04:05', 'UTC');
        $invitation->forceFill(['expires_at' => $expiresAt])->save();

        $invitation->forceFill(['status' => InvitationStatus::Declined])->save();

        $this->assertSame($expiresAt->toIso8601String(), $invitation->fresh()->expires_at->utc()->toIso8601String());
    }
}
