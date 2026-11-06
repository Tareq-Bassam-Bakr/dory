<?php

namespace Tests\Feature\Api\V1\Discovery;

use App\Enums\BusinessRole;
use App\Enums\TicketStatus;
use App\Models\User;
use App\Services\Discovery\GeoCircle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Nearby search, and the public business, branch and service pages.
 */
class CatalogueDetailsTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsTenants, RefreshDatabase;

    private const DAMASCUS = ['latitude' => 33.5138, 'longitude' => 36.2765];

    // ----- Nearby ---------------------------------------------------------

    public function test_nearby_branches_are_sorted_by_real_distance(): void
    {
        [, $far] = $this->publicBusiness('Far', ['latitude' => 33.60, 'longitude' => 36.35]);
        [, $near] = $this->publicBusiness('Near', ['latitude' => 33.52, 'longitude' => 36.28]);

        $response = $this->getJson($this->discovery('nearby', self::DAMASCUS + ['radius_km' => 25]))->assertOk();

        $this->assertSame([$near->public_id, $far->public_id], collect($response->json('data'))->pluck('id')->all());
        $this->assertEqualsWithDelta(
            GeoCircle::haversineKm(33.5138, 36.2765, 33.52, 36.28),
            $response->json('data.0.distance_km'),
            0.01,
        );
        $response->assertJsonPath('data.0.business.name', 'Near');
    }

    public function test_nearby_needs_a_location(): void
    {
        $this->getJson($this->discovery('nearby'))->assertStatus(422)->assertJsonValidationErrors(['latitude', 'longitude'], 'errors');
    }

    public function test_nearby_hides_everything_not_public(): void
    {
        [$inactive] = $this->publicBusiness('Inactive');
        $this->setStatus($inactive, 'inactive');
        [, $closed] = $this->publicBusiness('Closed branch');
        $this->setStatus($closed, 'inactive');
        $this->publicBusiness('No coordinates', ['latitude' => null, 'longitude' => null]);
        $this->publicBusiness('Too far', ['latitude' => 35.0, 'longitude' => 38.0]);
        $this->publicBusiness('Visible');

        $response = $this->getJson($this->discovery('nearby', self::DAMASCUS))->assertOk();

        $this->assertSame(['Visible'], collect($response->json('data'))->pluck('business.name')->all());
    }

    public function test_nearby_filters_by_queue_state(): void
    {
        [, $open] = $this->publicBusiness('Open');
        $this->openQueueIn($open);
        $this->publicBusiness('No queue');

        $response = $this->getJson($this->discovery('nearby', self::DAMASCUS + ['accepting' => 1]))->assertOk();

        $this->assertSame(['Open'], collect($response->json('data'))->pluck('business.name')->all());
        $response->assertJsonPath('data.0.accepting_queues_count', 1);
    }

    public function test_nearby_works_across_the_180th_meridian(): void
    {
        $this->publicBusiness('East of the line', ['latitude' => 0, 'longitude' => 179.95]);
        $this->publicBusiness('West of the line', ['latitude' => 0, 'longitude' => -179.95]);

        $response = $this->getJson($this->discovery('nearby', ['latitude' => 0, 'longitude' => 180, 'radius_km' => 20]))->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_the_query_count_of_nearby_does_not_grow_with_the_page(): void
    {
        $count = function (int $branches): int {
            foreach (range(1, $branches) as $number) {
                [, $branch] = $this->publicBusiness('Business '.$number.fake()->unique()->word());
                $this->openQueueIn($branch);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($this->discovery('nearby', self::DAMASCUS + ['per_page' => 100]))->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $small = $count(2);
        $large = $count(8);

        $this->assertSame($small, $large);
    }

    // ----- Business page --------------------------------------------------

    public function test_the_business_page_opens_by_slug_or_id_with_contact_details(): void
    {
        [$business] = $this->publicBusiness('Shifa Clinic');
        $business->forceFill(['description' => 'Family medicine', 'email' => 'hello@shifa.test', 'phone' => '+963933000000', 'website' => 'https://shifa.test'])->save();

        $this->assertApiSuccess($this->getJson($this->discovery('businesses/'.$business->slug)))
            ->assertJsonPath('data.name', 'Shifa Clinic')
            ->assertJsonPath('data.description', 'Family medicine')
            ->assertJsonPath('data.email', 'hello@shifa.test')
            ->assertJsonPath('data.phone', '+963933000000')
            ->assertJsonPath('data.website', 'https://shifa.test');

        $this->getJson($this->discovery('businesses/'.$business->public_id))->assertOk();
    }

    public function test_a_business_that_is_not_public_looks_like_it_does_not_exist(): void
    {
        [$suspended] = $this->publicBusiness('Suspended');
        $this->setStatus($suspended, 'suspended');

        $hidden = $this->assertApiError($this->getJson($this->discovery('businesses/'.$suspended->slug)), 404, 'business_not_found');
        $unknown = $this->assertApiError($this->getJson($this->discovery('businesses/no-such-business')), 404, 'business_not_found');

        $this->assertSame($unknown->json('message'), $hidden->json('message'));
    }

    public function test_the_business_page_lists_public_branches_nearest_first(): void
    {
        [$business] = $this->publicBusiness('Chain', ['name' => 'North', 'latitude' => 33.70, 'longitude' => 36.30]);
        $this->branchOf($business, ['name' => 'South', 'latitude' => 33.52, 'longitude' => 36.28]);
        $this->branchOf($business, ['name' => 'Nowhere', 'latitude' => null, 'longitude' => null]);
        $this->branchOf($business, ['name' => 'Closed', 'status' => 'inactive']);

        $response = $this->getJson($this->discovery('businesses/'.$business->slug.'/branches', self::DAMASCUS))->assertOk();

        $this->assertSame(['South', 'North', 'Nowhere'], collect($response->json('data'))->pluck('name')->all());
        $this->assertNull($response->json('data.2.distance_km'));
    }

    // ----- Branch page and services ---------------------------------------

    public function test_the_branch_page_shows_public_details_only(): void
    {
        [$business, $branch] = $this->publicBusiness('Shifa', ['address' => 'Mazzeh 12', 'phone' => '+963933000001', 'email' => 'branch@shifa.test']);
        $this->memberOf($business, BusinessRole::Manager);

        $response = $this->assertApiSuccess($this->getJson($this->discovery('branches/'.$branch->public_id)))
            ->assertJsonPath('data.address', 'Mazzeh 12')
            ->assertJsonPath('data.business.slug', $business->slug)
            ->assertJsonMissingPath('data.permissions')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.opening_hours');

        $this->assertStringNotContainsString('branch@shifa.test', $response->getContent());
    }

    public function test_inactive_branches_are_not_presented(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        $this->setStatus($branch, 'inactive');

        $this->assertApiError($this->getJson($this->discovery('branches/'.$branch->public_id)), 404, 'branch_not_found');
        $this->assertApiError($this->getJson($this->discovery('branches/'.$branch->public_id.'/services')), 404, 'branch_not_found');
    }

    public function test_a_branch_of_a_business_that_is_not_public_is_not_presented(): void
    {
        [$business, $branch] = $this->publicBusiness('Shifa');
        $this->setStatus($business, 'inactive');

        $this->assertApiError($this->getJson($this->discovery('branches/'.$branch->public_id)), 404, 'branch_not_found');
    }

    public function test_services_list_active_ones_with_their_queue(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [$service, $queue, $session] = $this->openQueueIn($branch, ['estimated_service_duration' => 15, 'capacity' => 5, 'require_guest_phone' => true]);
        $this->ticketIn($session);
        $this->setStatus($this->serviceOf($branch, ['name' => 'Hidden']), 'inactive');
        $this->serviceOf($branch, ['name' => 'No queue yet']);

        $response = $this->assertApiSuccess($this->getJson($this->discovery('branches/'.$branch->public_id.'/services')));
        $services = collect($response->json('data'));

        $this->assertSame(['Consultation', 'No queue yet'], $services->pluck('name')->sort()->values()->all());
        $this->assertNull($services->firstWhere('name', 'No queue yet')['queue']);

        $listed = $services->firstWhere('id', $service->public_id)['queue'];
        $this->assertSame($queue->public_id, $listed['id']);
        $this->assertSame('open', $listed['status']);
        $this->assertTrue($listed['accepting_joins']);
        $this->assertFalse($listed['is_full']);
        $this->assertSame(1, $listed['waiting_count']);
        $this->assertSame(4, $listed['capacity_remaining']);
        $this->assertSame(15, $listed['estimated_wait']['minutes']);
        $this->assertTrue($listed['allow_guest_join']);
        $this->assertTrue($listed['require_guest_phone']);
    }

    public function test_a_full_paused_closed_or_inactive_queue_does_not_accept_joins(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [$fullService, , $fullSession] = $this->openQueueIn($branch, ['capacity' => 1], service: 'Full');
        $this->ticketIn($fullSession);
        [$pausedService, , $pausedSession] = $this->openQueueIn($branch, service: 'Paused');
        $pausedSession->forceFill(['status' => 'paused'])->save();
        [$closedService] = $this->openQueueIn($branch, open: false, service: 'Closed');
        [$inactiveService] = $this->openQueueIn($branch, ['status' => 'inactive'], open: false, service: 'Inactive');

        $queues = collect($this->getJson($this->discovery('branches/'.$branch->public_id.'/services'))->json('data'))
            ->keyBy('id')
            ->map(fn (array $service) => $service['queue']);

        $this->assertTrue($queues[$fullService->public_id]['is_full']);
        $this->assertSame('open', $queues[$fullService->public_id]['status']);
        $this->assertSame('paused', $queues[$pausedService->public_id]['status']);
        $this->assertSame('closed', $queues[$closedService->public_id]['status']);
        $this->assertSame('inactive', $queues[$inactiveService->public_id]['status']);
        $this->assertSame('queue_inactive', $queues[$inactiveService->public_id]['unavailable_reason']);
        foreach ($queues as $queue) {
            $this->assertFalse($queue['accepting_joins']);
        }
    }

    public function test_yesterdays_session_does_not_make_a_queue_look_open(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC'));
        [$business, $branch] = $this->publicBusiness('Shifa');
        [, $queue] = $this->openQueueIn($branch);
        $this->actingAsUser($business->members()->with('user')->firstOrFail()->user, 'POST', $this->queueUri($queue, 'close'))->assertOk();
        $this->forgetAuthenticatedUser();
        $this->flushHeaders();

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00:00', 'UTC'));

        $this->getJson($this->discovery('branches/'.$branch->public_id.'/services'))
            ->assertJsonPath('data.0.queue.status', 'closed')
            ->assertJsonPath('data.0.queue.accepting_joins', false);
    }

    public function test_public_queue_data_never_contains_people(): void
    {
        [, $branch] = $this->publicBusiness('Shifa');
        [, , $session] = $this->openQueueIn($branch);
        $customer = User::factory()->create(['name' => 'Private Person', 'email' => 'private@example.com']);
        $this->ticketIn($session, TicketStatus::Waiting, ['user_id' => $customer->getKey()]);
        $this->ticketIn($session, TicketStatus::Waiting, ['guest_name' => 'Secret Guest', 'guest_phone' => '+963933111222']);

        foreach ([
            $this->discovery('businesses'),
            $this->discovery('nearby', self::DAMASCUS),
            $this->discovery('queues'),
            $this->discovery('branches/'.$branch->public_id),
            $this->discovery('branches/'.$branch->public_id.'/services'),
        ] as $uri) {
            $text = $this->getJson($uri)->assertOk()->getContent();

            foreach (['Private Person', 'private@example.com', 'Secret Guest', '+963933111222', 'access_token', 'public_token'] as $secret) {
                $this->assertStringNotContainsString($secret, $text, "{$uri} leaked {$secret}.");
            }
        }
    }

    // ----- Available queues and cities ------------------------------------

    public function test_available_queues_list_only_joinable_ones_lowest_wait_first(): void
    {
        [, $slow] = $this->publicBusiness('Slow');
        [, , $session] = $this->openQueueIn($slow, ['estimated_service_duration' => 30]);
        $this->ticketIn($session);
        [, $fast] = $this->publicBusiness('Fast');
        [, , $fastSession] = $this->openQueueIn($fast, ['estimated_service_duration' => 5]);
        $this->ticketIn($fastSession);
        [, $closed] = $this->publicBusiness('Closed');
        $this->openQueueIn($closed, open: false);
        [, $full] = $this->publicBusiness('Full');
        [, , $fullSession] = $this->openQueueIn($full, ['capacity' => 1]);
        $this->ticketIn($fullSession);

        $response = $this->assertApiSuccess($this->getJson($this->discovery('queues')));

        $this->assertSame(['Fast', 'Slow'], collect($response->json('data'))->pluck('business.name')->all());
        $response->assertJsonPath('data.0.service.name', 'Consultation')
            ->assertJsonPath('data.0.estimated_wait.minutes', 5)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    public function test_available_queues_can_be_limited_to_an_area_and_sorted_by_distance(): void
    {
        [, $far] = $this->publicBusiness('Far', ['latitude' => 33.60, 'longitude' => 36.35]);
        $this->openQueueIn($far);
        [, $near] = $this->publicBusiness('Near', ['latitude' => 33.52, 'longitude' => 36.28]);
        $this->openQueueIn($near);
        [, $outside] = $this->publicBusiness('Outside', ['latitude' => 35.0, 'longitude' => 38.0]);
        $this->openQueueIn($outside);

        $response = $this->getJson($this->discovery('queues', self::DAMASCUS + ['radius_km' => 25, 'sort' => 'nearest']))->assertOk();

        $this->assertSame(['Near', 'Far'], collect($response->json('data'))->pluck('business.name')->all());
        $this->assertNotNull($response->json('data.0.branch.distance_km'));
    }

    public function test_cities_come_from_public_branches(): void
    {
        $this->publicBusiness('A', ['city' => 'Damascus', 'country' => 'SY', 'latitude' => 33.50, 'longitude' => 36.20]);
        $this->publicBusiness('B', ['city' => 'Damascus', 'country' => 'SY', 'latitude' => 33.60, 'longitude' => 36.40]);
        $this->publicBusiness('C', ['city' => 'Cairo', 'country' => 'EG', 'latitude' => 30.04, 'longitude' => 31.23]);
        [$hidden] = $this->publicBusiness('D', ['city' => 'Hiddenville', 'country' => 'SY']);
        $this->setStatus($hidden, 'suspended');

        $response = $this->assertApiSuccess($this->getJson($this->discovery('cities')));

        $this->assertSame(['Damascus', 'Cairo'], collect($response->json('data'))->pluck('city')->all());
        $response->assertJsonPath('data.0.branches_count', 2)->assertJsonPath('meta.pagination.total', 2);
        $this->assertEqualsWithDelta(33.55, $response->json('data.0.latitude'), 0.0001);
        $this->assertEqualsWithDelta(36.30, $response->json('data.0.longitude'), 0.0001);

        $this->assertSame(['Cairo'], collect($this->getJson($this->discovery('cities', ['q' => 'cai']))->json('data'))->pluck('city')->all());
    }

    public function test_malformed_ids_never_reach_the_database_layer(): void
    {
        foreach (["1' OR '1'='1", '../../etc/passwd', '1', str_repeat('a', 27)] as $id) {
            $this->assertSame(404, $this->getJson('/api/v1/discovery/branches/'.rawurlencode($id))->status());
        }
    }
}
