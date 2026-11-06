<?php

namespace Tests\Feature\Security;

use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Guards against routes added without authentication or rate limits.
 */
class RouteProtectionTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    /**
     * The only API routes reachable without a bearer token.
     */
    private const PUBLIC_ROUTES = [
        'api.v1.health',
        'api.v1.auth.register',
        'api.v1.auth.login',
        'api.v1.auth.password.forgot',
        'api.v1.auth.password.reset',
        'api.v1.business-categories.index',
        'api.v1.invitations.show',
        // Guest queue access: the queue token is the link secret, and guest
        // ticket routes additionally require the X-Ticket-Token header.
        'api.v1.public.queues.show',
        'api.v1.public.queues.join',
        'api.v1.public.tickets.show',
        'api.v1.public.tickets.cancel',
        // The same guest access by queue id, for queues found in discovery.
        'api.v1.public.queues.by-id.show',
        'api.v1.public.queues.by-id.join',
        // Public discovery: only public businesses, branches and services.
        'api.v1.discovery.businesses',
        'api.v1.discovery.businesses.show',
        'api.v1.discovery.businesses.branches',
        'api.v1.discovery.nearby',
        'api.v1.discovery.queues',
        'api.v1.discovery.cities',
        'api.v1.discovery.branches.show',
        'api.v1.discovery.branches.services',
    ];

    /**
     * Guest ticket routes are unauthenticated, so each must check the
     * ticket access token.
     */
    private const TICKET_TOKEN_ROUTES = [
        'api.v1.public.tickets.show',
        'api.v1.public.tickets.cancel',
    ];

    /**
     * @return list<RouteDefinition>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/v1/') && ! str_contains($route->uri(), 'test-probe'),
        ));
    }

    public function test_guest_ticket_routes_require_the_ticket_access_token(): void
    {
        foreach ($this->apiRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/public/tickets')) {
                $this->assertContains($route->getName(), self::TICKET_TOKEN_ROUTES, "Unexpected guest ticket route {$route->uri()}.");
            }
            if (in_array($route->getName(), self::TICKET_TOKEN_ROUTES, true)) {
                $this->assertContains('ticket.token', $route->gatherMiddleware(), "Route {$route->uri()} does not check the ticket token.");
            }
        }
    }

    public function test_every_non_public_api_route_requires_an_active_authenticated_user(): void
    {
        foreach ($this->apiRoutes() as $route) {
            if (in_array($route->getName(), self::PUBLIC_ROUTES, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:sanctum', $middleware, "Route {$route->uri()} is not authenticated.");
            $this->assertContains('active', $middleware, "Route {$route->uri()} does not reject suspended accounts.");
        }
    }

    public function test_every_api_route_is_rate_limited(): void
    {
        foreach ($this->apiRoutes() as $route) {
            $limited = collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'))
                || in_array('api', $route->gatherMiddleware(), true);

            $this->assertTrue($limited, "Route {$route->uri()} has no rate limit.");
        }
    }

    public function test_invitation_token_lookups_are_throttled_per_ip(): void
    {
        config(['dory.rate_limits.invitation_token' => 3]);

        foreach (range(1, 3) as $attempt) {
            $this->getJson('/api/v1/invitations/'.Str::random(64))->assertNotFound();
        }

        $this->assertApiError($this->getJson('/api/v1/invitations/'.Str::random(64)), 429, 'too_many_requests');
    }

    public function test_sending_invitations_is_throttled_per_user(): void
    {
        config(['dory.rate_limits.invitations' => 2]);
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner);
        $uri = '/api/v1/businesses/'.$business->public_id.'/invitations';

        $this->actingAsUser($owner, 'POST', $uri, ['email' => 'a@example.com', 'role' => 'employee'])->assertCreated();
        $this->actingAsUser($owner, 'POST', $uri, ['email' => 'b@example.com', 'role' => 'employee'])->assertCreated();
        $this->assertApiError(
            $this->actingAsUser($owner, 'POST', $uri, ['email' => 'c@example.com', 'role' => 'employee']),
            429,
            'too_many_requests',
        );
        $this->assertSame(2, BusinessInvitation::query()->count());
    }

    public function test_malformed_ids_never_reach_the_database_layer(): void
    {
        $owner = User::factory()->create();

        foreach (["1' OR '1'='1", '../../etc/passwd', '1', str_repeat('a', 27)] as $id) {
            $this->assertSame(404, $this->actingAsUser($owner, 'GET', '/api/v1/branches/'.rawurlencode($id))->status());
        }
    }
}
