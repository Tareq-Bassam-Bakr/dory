<?php

namespace Tests\Feature\Api\V1\Discovery;

use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

class BusinessDiscoveryTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsTenants, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $query
     * @return list<string>
     */
    private function names(array $query = []): array
    {
        return collect($this->getJson($this->discovery('businesses', $query))->assertOk()->json('data'))
            ->pluck('name')
            ->all();
    }

    private function ownerOf(Business $business): User
    {
        return $business->members()->with('user')->firstOrFail()->user;
    }

    // ----- What is public -------------------------------------------------

    public function test_only_public_businesses_are_listed_without_an_account(): void
    {
        $this->publicBusiness('Visible Clinic');
        [$inactive] = $this->publicBusiness('Inactive Clinic');
        [$suspended] = $this->publicBusiness('Suspended Clinic');
        [$archived] = $this->publicBusiness('Archived Clinic');
        [, $closedBranch] = $this->publicBusiness('Closed Branch Clinic');
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'No Branch Clinic']);

        $this->setStatus($inactive, 'inactive');
        $this->setStatus($suspended, 'suspended');
        $archived->delete();
        $this->setStatus($closedBranch, 'inactive');

        $this->assertSame(['Visible Clinic'], $this->names());
    }

    public function test_a_card_exposes_only_public_fields(): void
    {
        [$business, $branch] = $this->publicBusiness('Shifa Clinic');
        $this->memberOf($business, BusinessRole::Employee);
        $this->openQueueIn($branch);

        $response = $this->assertApiSuccess($this->getJson($this->discovery('businesses')));
        $card = $response->json('data.0');

        $this->assertSame([
            'accepting_queues_count', 'branches_count', 'category', 'city', 'country', 'cover_url', 'distance_km',
            'id', 'is_favorite', 'logo_url', 'lowest_wait_minutes', 'name', 'open_queues_count', 'rating', 'slug',
        ], collect($card)->keys()->sort()->values()->all());
        $this->assertSame($business->public_id, $card['id']);

        $text = $response->getContent();
        $this->assertStringNotContainsString($this->ownerOf($business)->email, $text);
        $this->assertStringNotContainsString('created_by', $text);
        $this->assertStringNotContainsString('members', $text);
        $this->assertStringNotContainsString('idempotency', $text);
    }

    public function test_live_queue_numbers_come_from_the_current_session(): void
    {
        [$business, $branch] = $this->publicBusiness('Shifa Clinic');
        [, $queue, $session] = $this->openQueueIn($branch, ['estimated_service_duration' => 10]);
        $this->ticketIn($session);
        $this->ticketIn($session);

        $this->getJson($this->discovery('businesses'))
            ->assertJsonPath('data.0.open_queues_count', 1)
            ->assertJsonPath('data.0.accepting_queues_count', 1)
            ->assertJsonPath('data.0.lowest_wait_minutes', 20);

        // A closed session never makes a queue look open.
        $this->actingAsUser($this->ownerOf($business), 'POST', $this->queueUri($queue, 'close'))->assertOk();
        $this->forgetAuthenticatedUser();
        $this->flushHeaders();

        $this->getJson($this->discovery('businesses'))
            ->assertJsonPath('data.0.open_queues_count', 0)
            ->assertJsonPath('data.0.lowest_wait_minutes', null);
    }

    // ----- Search ---------------------------------------------------------

    public function test_search_matches_the_business_name_and_ranks_it_first(): void
    {
        $this->publicBusiness('Medical Center', ['city' => 'Homs']);
        $this->publicBusiness('Downtown Barber');
        $this->publicBusiness('Barber');
        $this->publicBusiness('Barber Shop Damascus');

        $this->assertSame(['Barber', 'Barber Shop Damascus', 'Downtown Barber'], $this->names(['q' => 'barber']));
    }

    public function test_search_matches_categories_in_both_languages(): void
    {
        $category = BusinessCategory::factory()->create(['name' => ['en' => 'Healthcare', 'ar' => 'الرعاية الصحية']]);
        $this->publicBusiness('Shifa', category: $category);
        $this->publicBusiness('Unrelated');

        $this->assertSame(['Shifa'], $this->names(['q' => 'healthcare']));
        $this->assertSame(['Shifa'], $this->names(['q' => 'الصحية']));
    }

    public function test_search_matches_services_cities_branches_and_countries(): void
    {
        [, $branch] = $this->publicBusiness('Alpha', ['city' => 'Aleppo', 'country' => 'SY', 'address' => 'Queen Street 4']);
        $this->openQueueIn($branch, service: 'Dental cleaning');
        $this->publicBusiness('Beta', ['city' => 'Cairo', 'country' => 'EG', 'name' => 'Nile branch']);

        $this->assertSame(['Alpha'], $this->names(['q' => 'dental']));
        $this->assertSame(['Alpha'], $this->names(['q' => 'aleppo']));
        $this->assertSame(['Alpha'], $this->names(['q' => 'queen']));
        $this->assertSame(['Beta'], $this->names(['q' => 'nile']));
        $this->assertSame(['Beta'], $this->names(['q' => 'eg']));
    }

    public function test_inactive_services_and_branches_do_not_match_a_search(): void
    {
        [$business, $branch] = $this->publicBusiness('Alpha');
        $this->setStatus($this->serviceOf($branch, ['name' => 'Hidden therapy']), 'inactive');
        $this->branchOf($business, ['name' => 'Secret annex', 'status' => 'inactive']);

        $this->assertSame([], $this->names(['q' => 'therapy']));
        $this->assertSame([], $this->names(['q' => 'annex']));
    }

    public function test_like_wildcards_in_a_search_are_literal(): void
    {
        $this->publicBusiness('100% Fresh');
        $this->publicBusiness('Plain');

        $this->assertSame(['100% Fresh'], $this->names(['q' => '100%']));
        $this->assertSame([], $this->names(['q' => '%%%']));
        $this->assertSame([], $this->names(['q' => '_____________']));
    }

    public function test_sql_in_a_search_is_only_text(): void
    {
        $this->publicBusiness('Alpha');

        $this->assertSame([], $this->names(['q' => "' OR 1=1 --"]));
        $this->assertSame(1, Business::query()->count());
    }

    // ----- Filters --------------------------------------------------------

    public function test_filtering_by_category_country_city_and_service(): void
    {
        $clinics = BusinessCategory::factory()->create(['slug' => 'clinics']);
        $this->publicBusiness('Alpha', ['city' => 'Damascus', 'country' => 'SY'], $clinics);
        [, $beta] = $this->publicBusiness('Beta', ['city' => 'Cairo', 'country' => 'EG']);
        $this->openQueueIn($beta, service: 'Haircut');

        $this->assertSame(['Alpha'], $this->names(['category' => 'clinics']));
        $this->assertSame(['Beta'], $this->names(['country' => 'eg']));
        $this->assertSame(['Alpha'], $this->names(['city' => 'Damascus']));
        $this->assertSame(['Beta'], $this->names(['service' => 'haircut']));
        $this->assertSame([], $this->names(['category' => 'no-such-category']));
    }

    public function test_a_parent_category_includes_its_subcategories(): void
    {
        $parent = BusinessCategory::factory()->create(['slug' => 'health']);
        $child = BusinessCategory::factory()->create(['slug' => 'dentists']);
        $child->forceFill(['parent_id' => $parent->getKey()])->save();
        $this->publicBusiness('Tooth', category: $child);

        $this->assertSame(['Tooth'], $this->names(['category' => 'health']));
    }

    public function test_queue_filters_distinguish_open_from_accepting(): void
    {
        [, $open] = $this->publicBusiness('Open');
        $this->openQueueIn($open);

        [, $full] = $this->publicBusiness('Full');
        [, , $session] = $this->openQueueIn($full, ['capacity' => 1]);
        $this->ticketIn($session);

        [, $closed] = $this->publicBusiness('Closed');
        $this->openQueueIn($closed, open: false);

        [, $paused] = $this->publicBusiness('Paused');
        [, , $pausedSession] = $this->openQueueIn($paused);
        $pausedSession->forceFill(['status' => 'paused'])->save();

        $this->assertSame(['Full', 'Open'], $this->names(['open_queue' => 1, 'sort' => 'rating']));
        $this->assertSame(['Open'], $this->names(['accepting' => 1]));
    }

    public function test_maximum_wait_and_sorting_by_wait_use_the_estimator(): void
    {
        [, $long] = $this->publicBusiness('Long wait');
        [, , $session] = $this->openQueueIn($long, ['estimated_service_duration' => 30]);
        $this->ticketIn($session);

        [, $short] = $this->publicBusiness('Short wait');
        [, , $other] = $this->openQueueIn($short, ['estimated_service_duration' => 5]);
        $this->ticketIn($other);

        [, $closed] = $this->publicBusiness('Closed');
        $this->openQueueIn($closed, open: false);

        $this->assertSame(['Short wait', 'Long wait'], $this->names(['sort' => 'wait']));
        $this->assertSame(['Short wait'], $this->names(['max_wait' => 10]));
    }

    public function test_sorting_by_wait_is_paginated(): void
    {
        foreach (['A', 'B', 'C'] as $index => $name) {
            [, $branch] = $this->publicBusiness($name);
            [, , $session] = $this->openQueueIn($branch, ['estimated_service_duration' => 30 - $index * 10]);
            $this->ticketIn($session);
        }

        $this->getJson($this->discovery('businesses', ['sort' => 'wait', 'per_page' => 2, 'page' => 2]))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'A')
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    public function test_sorting_by_nearest_uses_the_closest_branch(): void
    {
        $this->publicBusiness('Far', ['latitude' => 33.60, 'longitude' => 36.30]);
        [$near] = $this->publicBusiness('Near', ['latitude' => 33.80, 'longitude' => 36.60]);
        $this->branchOf($near, ['latitude' => 33.5139, 'longitude' => 36.2766]);

        $response = $this->getJson($this->discovery('businesses', [
            'latitude' => 33.5138, 'longitude' => 36.2765, 'radius_km' => 60, 'sort' => 'nearest',
        ]))->assertOk();

        $this->assertSame(['Near', 'Far'], collect($response->json('data'))->pluck('name')->all());
        $this->assertLessThan(0.1, $response->json('data.0.distance_km'));
    }

    public function test_a_radius_limits_results_to_the_area(): void
    {
        $this->publicBusiness('Inside', ['latitude' => 33.52, 'longitude' => 36.28]);
        $this->publicBusiness('Outside', ['latitude' => 34.72, 'longitude' => 36.72]);
        $this->publicBusiness('Unknown place', ['latitude' => null, 'longitude' => null]);

        $this->assertSame(['Inside'], $this->names(['latitude' => 33.5138, 'longitude' => 36.2765, 'radius_km' => 10]));
    }

    public function test_ratings_are_shown_only_with_enough_real_reviews(): void
    {
        config(['dory.discovery.min_ratings' => 3]);
        [$few] = $this->publicBusiness('Few');
        $few->forceFill(['rating_average' => 5, 'rating_count' => 2])->save();
        [$many] = $this->publicBusiness('Many');
        $many->forceFill(['rating_average' => 4.25, 'rating_count' => 8])->save();
        $this->publicBusiness('None');

        $cards = collect($this->getJson($this->discovery('businesses', ['sort' => 'rating']))->json('data'));

        $this->assertSame('Many', $cards[0]['name']);
        $this->assertEquals(['average' => 4.25, 'count' => 8], $cards[0]['rating']);
        $this->assertNull($cards->firstWhere('name', 'Few')['rating']);
        $this->assertNull($cards->firstWhere('name', 'None')['rating']);
        $this->assertSame(['Many'], $this->names(['min_rating' => 4]));
    }

    // ----- Paging and input -----------------------------------------------

    public function test_results_are_paginated(): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            $this->publicBusiness($name);
        }

        $this->getJson($this->discovery('businesses', ['per_page' => 2, 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('meta.pagination.last_page', 2);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidQueries(): array
    {
        return [
            'latitude out of range' => [['latitude' => 91, 'longitude' => 0], 'latitude'],
            'longitude out of range' => [['latitude' => 0, 'longitude' => 181], 'longitude'],
            'latitude without longitude' => [['latitude' => 33], 'longitude'],
            'longitude without latitude' => [['longitude' => 36], 'latitude'],
            'radius too large' => [['latitude' => 33, 'longitude' => 36, 'radius_km' => 5000], 'radius_km'],
            'page too large' => [['per_page' => 1000], 'per_page'],
            'nearest without a location' => [['sort' => 'nearest'], 'latitude'],
            'unknown sort' => [['sort' => 'random'], 'sort'],
            'country is a code' => [['country' => 'Syria'], 'country'],
            'query too long' => [['q' => str_repeat('a', 101)], 'q'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidQueries')]
    public function test_invalid_input_is_rejected(array $query, string $field): void
    {
        $this->getJson($this->discovery('businesses', $query))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field], 'errors');
    }

    public function test_the_query_count_does_not_grow_with_the_page(): void
    {
        $count = function (int $businesses): int {
            foreach (range(1, $businesses) as $number) {
                [, $branch] = $this->publicBusiness('Business '.$number.' '.fake()->unique()->word());
                [, , $session] = $this->openQueueIn($branch);
                $this->ticketIn($session);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->discovery('businesses', ['per_page' => 50]))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $small = $count(2);
        $large = $count(10);

        $this->assertSame($small, $large);
    }

    public function test_discovery_is_rate_limited_per_client(): void
    {
        config(['dory.rate_limits.discovery' => 2]);

        $this->getJson($this->discovery('businesses'))->assertOk();
        $this->getJson($this->discovery('businesses'))->assertOk();

        $this->assertApiError($this->getJson($this->discovery('businesses')), 429, 'too_many_requests');
    }

    public function test_favorites_are_marked_only_for_the_signed_in_viewer(): void
    {
        [$business] = $this->publicBusiness('Saved');
        $customer = User::factory()->create();
        $customer->favoriteBusinesses()->attach($business->getKey());

        $this->actingAsUser($customer, 'GET', $this->discovery('businesses'))->assertJsonPath('data.0.is_favorite', true);
        $this->actingAsUser(User::factory()->create(), 'GET', $this->discovery('businesses'))->assertJsonPath('data.0.is_favorite', false);

        $this->forgetAuthenticatedUser();
        $this->flushHeaders();
        $this->getJson($this->discovery('businesses'))->assertJsonPath('data.0.is_favorite', false);
    }

    public function test_a_finished_ticket_never_counts_as_waiting(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [, , $session] = $this->openQueueIn($branch, ['estimated_service_duration' => 10]);
        $this->ticketIn($session, TicketStatus::Cancelled);

        $this->getJson($this->discovery('businesses'))->assertJsonPath('data.0.lowest_wait_minutes', 0);
    }
}
