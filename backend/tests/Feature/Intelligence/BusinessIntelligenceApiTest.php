<?php

namespace Tests\Feature\Intelligence;

use App\Enums\BranchAccess;
use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Peak times and insights through the API (docs/AI_ARCHITECTURE.md): real
 * records only, honest about too little data, isolated per business and
 * branch.
 */
class BusinessIntelligenceApiTest extends TestCase
{
    use BuildsQueues, BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    private Branch $branch;

    private Queue $queue;

    private QueueSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        // A Monday, noon UTC: 15:00 in Riyadh (UTC+3, no daylight saving).
        $this->travelTo(Carbon::parse('2026-11-09 12:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner, ['name' => 'Shifa Clinic']);
        $this->branch = $this->branchOf($this->business, ['timezone' => 'Asia/Riyadh']);
        $this->queue = $this->queueFor($this->serviceOf($this->branch), ['name' => 'General', 'estimated_service_duration' => 8]);
        $this->session = $this->openSession($this->queue, $this->owner);
    }

    /**
     * A finished visit: joined at $joined (UTC), called after $wait minutes,
     * served for $service minutes when completed.
     */
    private function visit(Carbon $joined, TicketStatus $status = TicketStatus::Completed, int $wait = 10, int $service = 8, ?QueueSession $session = null, array $extra = []): QueueTicket
    {
        $called = $status === TicketStatus::Cancelled ? null : $joined->copy()->addMinutes($wait);

        return $this->ticketIn($session ?? $this->session, $status, $extra + [
            'joined_at' => $joined,
            'called_at' => $called,
            'started_at' => $status === TicketStatus::Completed ? $called : null,
            'completed_at' => $status === TicketStatus::Completed ? $called->copy()->addMinutes($service) : null,
        ]);
    }

    /**
     * @param  callable(int): void  $each
     */
    private function times(int $count, callable $each): void
    {
        foreach (range(0, $count - 1) as $i) {
            $each($i);
        }
    }

    private function uri(string $what, ?Business $business = null): string
    {
        return '/api/v1/businesses/'.($business ?? $this->business)->public_id.'/'.$what;
    }

    // ----- Peak times -------------------------------------------------------

    public function test_peak_times_say_when_there_is_not_enough_data(): void
    {
        $this->times(10, fn (int $i) => $this->visit(now()->subDays($i)->setTime(7, 0)));

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('peak-times')))
            ->assertJsonPath('data.status', 'insufficient_data')
            ->assertJsonPath('data.tickets', 10)
            ->assertJsonPath('data.minimum.tickets', 50)
            ->assertJsonPath('data.weekdays', [])
            ->assertJsonPath('data.busiest', []);
    }

    public function test_peak_times_find_the_busy_and_quiet_hours_in_local_time(): void
    {
        config(['dory.intelligence.forecast_minimum_tickets' => 10, 'dory.intelligence.forecast_minimum_days' => 14]);
        // Three Mondays, 4 customers at 07:00 UTC = 10:00 in Riyadh.
        foreach (['2026-10-26', '2026-11-02', '2026-11-09'] as $monday) {
            $this->times(4, fn (int $i) => $this->visit(Carbon::parse($monday.' 07:00', 'UTC')->addMinutes($i)));
        }
        // Two Wednesdays, one customer at 13:00 UTC = 16:00.
        foreach (['2026-10-28', '2026-11-04'] as $wednesday) {
            $this->visit(Carbon::parse($wednesday.' 13:00', 'UTC'));
        }

        $response = $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('peak-times')))
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.timezone', 'Asia/Riyadh')
            ->assertJsonPath('data.days_covered', 15)
            ->assertJsonPath('data.busiest.0', ['weekday' => 1, 'hour' => 10, 'average' => 4])
            ->assertJsonPath('data.weekdays.0.levels.10', 'busy')
            ->assertJsonPath('data.weekdays.2.levels.16', 'quiet')
            ->assertJsonPath('data.weekdays.0.levels.7', 'closed')
            ->assertJsonPath('data.upcoming.0.date', '2026-11-09')
            ->assertJsonPath('data.upcoming.0.peak_hour', 10);
        // 3 Mondays in the window: 12 / 3.
        $this->assertEquals(4.0, $response->json('data.weekdays.0.hours.10'));
        $this->assertEquals(1.0, $response->json('data.weekdays.2.hours.16'));
    }

    // ----- Insights -----------------------------------------------------------

    public function test_insights_show_metrics_but_no_advice_with_too_little_data(): void
    {
        $this->times(5, fn (int $i) => $this->visit(now()->subDays($i + 1)));

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('insights')))
            ->assertJsonPath('data.status', 'insufficient_data')
            ->assertJsonPath('data.minimum_tickets', 20)
            ->assertJsonPath('data.metrics.tickets', 5)
            ->assertJsonPath('data.metrics.completion_rate', 100)
            ->assertJsonPath('data.insights', [])
            ->assertJsonPath('data.generated_by', 'template');
    }

    public function test_missed_turns_and_long_waits_are_reported_with_their_numbers(): void
    {
        $this->times(12, fn (int $i) => $this->visit(now()->subDays($i + 1)->setTime(7, $i), wait: 40));
        $this->times(5, fn (int $i) => $this->visit(now()->subDays($i + 1)->setTime(8, 0), TicketStatus::NoShow, wait: 40));
        $this->times(3, fn (int $i) => $this->visit(now()->subDays($i + 1)->setTime(9, 0), TicketStatus::Cancelled, extra: ['cancel_reason' => 'customer_cancelled']));

        $response = $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('insights')))
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.metrics.tickets', 20)
            ->assertJsonPath('data.metrics.no_show_rate', 29)
            ->assertJsonPath('data.metrics.average_wait_minutes', 40)
            ->assertJsonPath('data.metrics.cancellation_rate', 15);

        $insights = collect($response->json('data.insights'))->keyBy('kind');
        $this->assertSame(29, $insights['no_show_high']['facts']['rate']);
        $this->assertSame('warning', $insights['no_show_high']['severity']);
        $this->assertStringContainsString('29%', $insights['no_show_high']['body']);
        // The busiest hour, in Riyadh time.
        $this->assertSame('10:00', $insights['long_waits']['facts']['hour']);
        $this->assertArrayNotHasKey('running_well', $insights->all());
    }

    public function test_insights_are_worded_in_arabic(): void
    {
        $this->times(15, fn (int $i) => $this->visit(now()->subDays($i + 1), wait: 40));
        $this->times(5, fn (int $i) => $this->visit(now()->subDays($i + 1), TicketStatus::NoShow));

        $response = $this->actingAsUser($this->owner, 'GET', $this->uri('insights'), headers: ['Accept-Language' => 'ar']);

        $insights = collect($response->json('data.insights'))->keyBy('kind');
        $this->assertSame('كثير من العملاء يفوّتون دورهم', $insights['no_show_high']['title']);
        $this->assertSame('الانتظار طويل', $insights['long_waits']['title']);
    }

    public function test_arabic_lists_of_hours_read_right_to_left(): void
    {
        // Found on a phone: "10:00, 11:00, 12:00" inside Arabic text read as
        // one left-to-right block with the commas on the wrong side.
        $this->times(30, fn (int $i) => $this->visit(now()->subDays(1 + $i % 20)->setTime(7 + $i % 3, 0)));
        $this->times(6, fn (int $i) => $this->visit(now()->subDays(1 + $i)->setTime(11 + $i, 0)));

        $insights = collect($this->actingAsUser($this->owner, 'GET', $this->uri('insights'), headers: ['Accept-Language' => 'ar'])
            ->json('data.insights'))->keyBy('kind');

        $this->assertStringContainsString("10:00\u{060C}\u{200F} 11:00\u{060C}\u{200F} 12:00", $insights['peak_concentration']['body']);
        $this->assertSame('10:00, 11:00, 12:00', $insights['peak_concentration']['facts']['hours']);
    }

    public function test_growth_and_a_wrong_service_duration_are_suggested(): void
    {
        $this->times(20, fn (int $i) => $this->visit(now()->subDays(31 + $i % 25)));
        $this->times(30, fn (int $i) => $this->visit(now()->subDays(1 + $i % 25), service: 15));

        $response = $this->actingAsUser($this->owner, 'GET', $this->uri('insights'));

        $response->assertJsonPath('data.metrics.previous_tickets', 20)->assertJsonPath('data.metrics.change_percent', 50);
        $insights = collect($response->json('data.insights'))->keyBy('kind');
        $this->assertSame(50, $insights['demand_up']['facts']['percent']);
        $this->assertSame(['queue' => 'General', 'actual' => 15, 'configured' => 8], $insights['service_time_drift']['facts']);
    }

    public function test_a_well_run_business_is_told_so(): void
    {
        $this->times(25, fn (int $i) => $this->visit(now()->subDays(1 + $i)->setTime(6 + $i % 8, 0), wait: 5));

        $insights = collect($this->actingAsUser($this->owner, 'GET', $this->uri('insights'))->json('data.insights'));

        $this->assertSame(['running_well'], $insights->pluck('kind')->all());
        $this->assertSame('positive', $insights->first()['severity']);
    }

    public function test_no_personal_data_is_returned(): void
    {
        $this->times(20, fn (int $i) => $this->visit(now()->subDays($i + 1), extra: [
            'guest_name' => 'Secret Person', 'guest_phone' => '+963944555666',
        ]));

        foreach (['insights', 'peak-times'] as $what) {
            $body = $this->actingAsUser($this->owner, 'GET', $this->uri($what))->getContent();
            $this->assertStringNotContainsString('Secret Person', $body);
            $this->assertStringNotContainsString('944555666', $body);
        }
    }

    // ----- Tenant isolation ---------------------------------------------------

    public function test_other_businesses_and_employees_cannot_read_insights(): void
    {
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);

        // Signed out first: actingAsUser keeps the user for later requests.
        foreach (['insights', 'peak-times'] as $what) {
            $this->getJson($this->uri($what))->assertUnauthorized();
        }
        foreach (['insights', 'peak-times'] as $what) {
            $this->assertApiError($this->actingAsUser($stranger, 'GET', $this->uri($what)), 404, 'business_not_found');
            $this->assertApiError($this->actingAsUser($employee, 'GET', $this->uri($what)), 403, 'forbidden');
        }
    }

    public function test_another_business_data_is_never_counted(): void
    {
        $otherOwner = User::factory()->create();
        $other = $this->businessOwnedBy($otherOwner);
        $otherQueue = $this->queueFor($this->serviceOf($this->branchOf($other)));
        $otherSession = $this->openSession($otherQueue, $otherOwner);
        $this->times(7, fn (int $i) => $this->visit(now()->subDays($i + 1), session: $otherSession));
        $this->times(3, fn (int $i) => $this->visit(now()->subDays($i + 1)));
        // One branch on its own is a Business plan feature (docs/SUBSCRIPTIONS.md).
        $this->onPlan($this->business, 'business');

        $this->actingAsUser($this->owner, 'GET', $this->uri('insights'))->assertJsonPath('data.metrics.tickets', 3);
        $this->actingAsUser($otherOwner, 'GET', $this->uri('insights', $other))->assertJsonPath('data.metrics.tickets', 7);
        // A branch of another business is not found here.
        $this->assertApiError(
            $this->actingAsUser($this->owner, 'GET', $this->uri('insights').'?branch='.$otherQueue->branch->public_id),
            404,
            'branch_not_found',
        );
    }

    public function test_a_branch_manager_sees_only_their_branches(): void
    {
        $malki = $this->branchOf($this->business, ['timezone' => 'Asia/Riyadh']);
        $malkiQueue = $this->queueFor($this->serviceOf($malki));
        $malkiSession = $this->openSession($malkiQueue, $this->owner);
        $this->times(4, fn (int $i) => $this->visit(now()->subDays($i + 1), session: $malkiSession));
        $this->times(6, fn (int $i) => $this->visit(now()->subDays($i + 1)));
        $manager = $this->memberOf($this->business, BusinessRole::Manager, BranchAccess::Selected, [$malki]);
        // One branch on its own is a Business plan feature (docs/SUBSCRIPTIONS.md).
        $this->onPlan($this->business, 'business');

        $this->actingAsUser($manager, 'GET', $this->uri('insights'))
            ->assertJsonPath('data.metrics.tickets', 4)
            ->assertJsonPath('data.branches', [$malki->public_id]);
        $this->assertApiError(
            $this->actingAsUser($manager, 'GET', $this->uri('insights').'?branch='.$this->branch->public_id),
            404,
            'branch_not_found',
        );
        // The owner sees both, or one branch on request.
        $this->actingAsUser($this->owner, 'GET', $this->uri('insights'))->assertJsonPath('data.metrics.tickets', 10);
        $this->actingAsUser($this->owner, 'GET', $this->uri('insights').'?branch='.$malki->public_id)
            ->assertJsonPath('data.metrics.tickets', 4);
    }

    public function test_owners_and_managers_get_the_permission_in_the_business(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $show = fn (User $user) => $this->actingAsUser($user, 'GET', '/api/v1/businesses/'.$this->business->public_id);

        $show($this->owner)->assertJsonPath('data.permissions.view_insights', true);
        $show($manager)->assertJsonPath('data.permissions.view_insights', true);
        $show($employee)->assertJsonPath('data.permissions.view_insights', false);
    }
}
