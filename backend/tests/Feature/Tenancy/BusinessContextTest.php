<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use App\Services\Business\CurrentBusinessContext;
use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use LogicException;
use Tests\TestCase;

/**
 * Tenant isolation through the `business.context` middleware.
 *
 * No business endpoints exist yet (Phase 03), so this test registers a probe
 * route wired exactly as future business routes will be.
 */
class BusinessContextTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE = '/api/v1/test-probe/businesses/%s/context';

    private BusinessMembershipService $memberships;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberships = app(BusinessMembershipService::class);

        Route::middleware(['api', 'auth:sanctum', 'active', 'business.context'])
            ->get('api/v1/test-probe/businesses/{business}/context', function (CurrentBusinessContext $context) {
                return ApiResponse::success([
                    'business' => $context->business()->public_id,
                    'role' => $context->role()->value,
                ]);
            });
    }

    private function probe(User $user, string $businessPublicId, array $query = [], array $headers = []): TestResponse
    {
        $uri = sprintf(self::PROBE, $businessPublicId).($query ? '?'.http_build_query($query) : '');

        return $this->withToken($this->tokenFor($user))->withHeaders($headers)->getJson($uri);
    }

    public function test_a_member_resolves_their_own_business(): void
    {
        $owner = User::factory()->create();
        $business = $this->memberships->createBusinessWithOwner($owner, 'Shifa Clinic');

        $this->assertApiSuccess($this->probe($owner, $business->public_id))
            ->assertJsonPath('data.business', $business->public_id)
            ->assertJsonPath('data.role', 'owner');
    }

    public function test_an_employee_gets_their_business_scoped_role(): void
    {
        $business = $this->memberships->createBusinessWithOwner(User::factory()->create(), 'City Bank');
        $employee = User::factory()->create();
        $this->memberships->addMember($business, $employee, BusinessRole::Employee);

        $this->probe($employee, $business->public_id)->assertOk()->assertJsonPath('data.role', 'employee');
    }

    public function test_a_user_cannot_access_another_tenants_business(): void
    {
        $alice = User::factory()->create();
        $mallory = User::factory()->create();
        $aliceBusiness = $this->memberships->createBusinessWithOwner($alice, 'Alice Salon');
        $this->memberships->createBusinessWithOwner($mallory, 'Mallory Repairs');

        $this->assertApiError($this->probe($mallory, $aliceBusiness->public_id), 404, 'business_not_found');
    }

    public function test_a_nonexistent_business_returns_the_same_not_found(): void
    {
        $user = User::factory()->create();

        $this->assertApiError($this->probe($user, '01JZZZZZZZZZZZZZZZZZZZZZZZ'), 404, 'business_not_found');
    }

    public function test_client_supplied_business_ids_are_ignored(): void
    {
        $alice = User::factory()->create();
        $aliceBusiness = $this->memberships->createBusinessWithOwner($alice, 'Alice Salon');
        $otherBusiness = $this->memberships->createBusinessWithOwner(User::factory()->create(), 'Other Clinic');

        // Attempt to smuggle another tenant's ids through query and headers.
        $response = $this->probe(
            $alice,
            $aliceBusiness->public_id,
            ['business_id' => $otherBusiness->id, 'business' => $otherBusiness->public_id],
            ['X-Business-Id' => (string) $otherBusiness->id],
        );

        $response->assertOk()->assertJsonPath('data.business', $aliceBusiness->public_id);
    }

    public function test_a_suspended_membership_cannot_resolve_the_business(): void
    {
        $user = User::factory()->create();
        $membership = BusinessMember::factory()->manager()->suspended()->create(['user_id' => $user->id]);

        $this->assertApiError($this->probe($user, $membership->business->public_id), 404, 'business_not_found');
    }

    public function test_the_business_context_requires_authentication(): void
    {
        $business = Business::factory()->create();

        $response = $this->getJson(sprintf(self::PROBE, $business->public_id));

        $this->assertApiError($response, 401, 'unauthenticated');
    }

    public function test_reading_an_unresolved_context_fails_loudly(): void
    {
        $this->expectException(LogicException::class);

        app(CurrentBusinessContext::class)->business();
    }
}
