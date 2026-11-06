<?php

namespace Tests\Feature\Api\V1\Customers;

use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Business;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\TicketReview;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class FavoritesAndReviewsTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsTenants, RefreshDatabase;

    private const FAVORITES = '/api/v1/me/favorites';

    // ----- Favorites ------------------------------------------------------

    public function test_a_customer_saves_lists_and_removes_a_business(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $customer = User::factory()->create();

        $this->assertApiSuccess($this->actingAsUser($customer, 'POST', self::FAVORITES, ['business' => $business->public_id]), 201)
            ->assertJsonPath('data.is_favorite', true);

        $this->actingAsUser($customer, 'GET', self::FAVORITES)
            ->assertOk()
            ->assertJsonPath('data.0.id', $business->public_id)
            ->assertJsonPath('data.0.is_favorite', true)
            ->assertJsonPath('meta.pagination.total', 1);

        $this->assertApiSuccess($this->actingAsUser($customer, 'DELETE', self::FAVORITES.'/'.$business->public_id));
        $this->actingAsUser($customer, 'GET', self::FAVORITES)->assertJsonCount(0, 'data');
    }

    public function test_saving_twice_creates_no_duplicate(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $customer = User::factory()->create();

        $this->actingAsUser($customer, 'POST', self::FAVORITES, ['business' => $business->public_id])->assertCreated();
        $this->actingAsUser($customer, 'POST', self::FAVORITES, ['business' => $business->public_id])->assertOk();

        $this->assertSame(1, DB::table('customer_favorites')->count());
    }

    public function test_the_database_refuses_a_duplicate_favorite(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $row = ['user_id' => User::factory()->create()->getKey(), 'business_id' => $business->getKey()];
        DB::table('customer_favorites')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('customer_favorites')->insert($row);
    }

    public function test_favorites_are_private_to_each_customer(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->favoriteBusinesses()->attach($business->getKey());

        $this->actingAsUser($other, 'GET', self::FAVORITES)->assertJsonCount(0, 'data');

        // Removing affects only the caller's own list.
        $this->actingAsUser($other, 'DELETE', self::FAVORITES.'/'.$business->public_id)->assertOk();
        $this->assertTrue($owner->favoriteBusinesses()->whereKey($business->getKey())->exists());
    }

    public function test_a_favorite_cannot_be_created_for_someone_else(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $victim = User::factory()->create();

        $this->actingAsUser(User::factory()->create(), 'POST', self::FAVORITES, [
            'business' => $business->public_id,
            'user_id' => $victim->public_id,
        ])->assertStatus(422)->assertJsonValidationErrors(['user_id'], 'errors');

        $this->assertSame(0, DB::table('customer_favorites')->count());
    }

    public function test_only_public_businesses_can_be_saved(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $this->setStatus($business, 'suspended');

        $this->assertApiError(
            $this->actingAsUser(User::factory()->create(), 'POST', self::FAVORITES, ['business' => $business->public_id]),
            404,
            'business_not_found',
        );
    }

    public function test_a_saved_business_that_stops_being_public_is_hidden_but_can_be_removed(): void
    {
        [$business] = $this->publicBusiness('Shifa');
        $customer = User::factory()->create();
        $customer->favoriteBusinesses()->attach($business->getKey());
        $this->setStatus($business, 'inactive');

        $this->actingAsUser($customer, 'GET', self::FAVORITES)->assertJsonCount(0, 'data');
        $this->actingAsUser($customer, 'DELETE', self::FAVORITES.'/'.$business->public_id)->assertOk();

        $this->assertSame(0, DB::table('customer_favorites')->count());
    }

    public function test_favorites_need_an_account(): void
    {
        [$business] = $this->publicBusiness('Shifa');

        $this->getJson(self::FAVORITES)->assertUnauthorized();
        $this->postJson(self::FAVORITES, ['business' => $business->public_id])->assertUnauthorized();
        $this->deleteJson(self::FAVORITES.'/'.$business->public_id)->assertUnauthorized();
    }

    public function test_invalid_business_ids_are_rejected(): void
    {
        $this->actingAsUser(User::factory()->create(), 'POST', self::FAVORITES, ['business' => "1' OR '1'='1"])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business'], 'errors');
    }

    // ----- Reviews --------------------------------------------------------

    /**
     * @return array{0: Business, 1: QueueSession}
     */
    private function ratedBusiness(): array
    {
        [$business, $branch] = $this->publicBusiness('Shifa');
        [, , $session] = $this->openQueueIn($branch);

        return [$business, $session];
    }

    private function completedTicket(QueueSession $session, User $customer): QueueTicket
    {
        return $this->ticketIn($session, TicketStatus::Completed, [
            'user_id' => $customer->getKey(),
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    private function reviewUri(QueueTicket $ticket): string
    {
        return '/api/v1/tickets/'.$ticket->public_id.'/review';
    }

    public function test_a_customer_rates_a_completed_ticket_once(): void
    {
        [$business, $session] = $this->ratedBusiness();
        $customer = User::factory()->create();
        $ticket = $this->completedTicket($session, $customer);

        $this->actingAsUser($customer, 'GET', '/api/v1/tickets/'.$ticket->public_id.'/position')->assertJsonPath('data.can_review', true);

        $this->assertApiSuccess($this->actingAsUser($customer, 'POST', $this->reviewUri($ticket), ['rating' => 4]), 201)
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.my_rating', 4);

        $this->assertApiError($this->actingAsUser($customer, 'POST', $this->reviewUri($ticket), ['rating' => 5]), 409, 'already_reviewed');

        $business->refresh();
        $this->assertSame(1, $business->rating_count);
        $this->assertEquals(4.0, $business->rating_average);
    }

    public function test_the_summary_is_the_real_average(): void
    {
        [$business, $session] = $this->ratedBusiness();

        foreach ([5, 4, 2] as $rating) {
            $customer = User::factory()->create();
            $this->actingAsUser($customer, 'POST', $this->reviewUri($this->completedTicket($session, $customer)), ['rating' => $rating])->assertCreated();
        }

        $business->refresh();
        $this->assertSame(3, $business->rating_count);
        $this->assertEquals(3.67, $business->rating_average);
    }

    public function test_tickets_that_are_not_completed_cannot_be_rated(): void
    {
        [, $session] = $this->ratedBusiness();
        $customer = User::factory()->create();

        foreach ([TicketStatus::Waiting, TicketStatus::Cancelled, TicketStatus::NoShow] as $status) {
            $ticket = $this->ticketIn($session, $status, ['user_id' => $customer->getKey()]);
            $this->assertApiError($this->actingAsUser($customer, 'POST', $this->reviewUri($ticket), ['rating' => 5]), 409, 'ticket_not_reviewable');
            if ($status === TicketStatus::Waiting) {
                $ticket->forceFill(['status' => TicketStatus::Cancelled])->save();
            }
        }

        $this->assertSame(0, TicketReview::query()->count());
    }

    public function test_someone_elses_ticket_cannot_be_rated(): void
    {
        [, $session] = $this->ratedBusiness();
        $ticket = $this->completedTicket($session, User::factory()->create());

        $this->assertApiError($this->actingAsUser(User::factory()->create(), 'POST', $this->reviewUri($ticket), ['rating' => 5]), 404, 'ticket_not_found');
    }

    public function test_a_business_team_cannot_rate_itself(): void
    {
        [$business, $session] = $this->ratedBusiness();
        $employee = $this->memberOf($business, BusinessRole::Employee);
        $ticket = $this->completedTicket($session, $employee);

        $this->assertApiError($this->actingAsUser($employee, 'POST', $this->reviewUri($ticket), ['rating' => 5]), 409, 'ticket_not_reviewable');
    }

    public function test_guests_cannot_rate(): void
    {
        [, $session] = $this->ratedBusiness();
        $ticket = $this->ticketIn($session, TicketStatus::Completed, [
            'guest_name' => 'Rami',
            'public_access_token_hash' => QueueTicket::hashAccessToken('guest-token'),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);

        $this->postJson($this->reviewUri($ticket), ['rating' => 5])->assertUnauthorized();
        $this->getJson('/api/v1/public/tickets/'.$ticket->public_id, ['X-Ticket-Token' => 'guest-token'])
            ->assertOk()
            ->assertJsonPath('data.can_review', false);
    }

    public function test_ratings_are_validated(): void
    {
        [, $session] = $this->ratedBusiness();
        $customer = User::factory()->create();
        $ticket = $this->completedTicket($session, $customer);

        foreach ([0, 6, 'great', null] as $rating) {
            $this->actingAsUser($customer, 'POST', $this->reviewUri($ticket), ['rating' => $rating])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['rating'], 'errors');
        }
    }

    public function test_the_database_refuses_a_second_review_of_a_ticket(): void
    {
        [$business, $session] = $this->ratedBusiness();
        $customer = User::factory()->create();
        $ticket = $this->completedTicket($session, $customer);
        $row = [
            'public_id' => strtolower((string) str()->ulid()),
            'queue_ticket_id' => $ticket->getKey(),
            'business_id' => $business->getKey(),
            'rating' => 5,
        ];
        DB::table('ticket_reviews')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('ticket_reviews')->insert(['public_id' => strtolower((string) str()->ulid())] + $row);
    }

    public function test_a_rating_does_not_touch_the_business_profile_date(): void
    {
        [$business, $session] = $this->ratedBusiness();
        $before = $business->refresh()->updated_at;
        $this->travel(1)->hours();

        $customer = User::factory()->create();
        $this->actingAsUser($customer, 'POST', $this->reviewUri($this->completedTicket($session, $customer)), ['rating' => 5])->assertCreated();

        $this->assertTrue($before->equalTo($business->refresh()->updated_at));
    }
}
