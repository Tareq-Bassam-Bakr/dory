<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Current user (me), logout, token revocation and token rejection.
 */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_their_profile_with_memberships(): void
    {
        $user = User::factory()->create();
        $business = app(BusinessMembershipService::class)->createBusinessWithOwner($user, 'Shifa Clinic');

        $response = $this->withToken($this->tokenFor($user))->getJson('/api/v1/auth/me');

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.id', $user->public_id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.memberships.0.role', 'owner')
            ->assertJsonPath('data.memberships.0.status', 'active')
            ->assertJsonPath('data.memberships.0.business.id', $business->public_id)
            ->assertJsonMissingPath('data.password');

        // No internal numeric identifiers leak anywhere in the payload.
        $this->assertStringNotContainsString('"business_id"', $response->getContent());
        $this->assertStringNotContainsString('"user_id"', $response->getContent());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'me' => ['GET', '/api/v1/auth/me'],
            'logout' => ['POST', '/api/v1/auth/logout'],
            'profile' => ['PUT', '/api/v1/users/profile'],
            'locale' => ['PUT', '/api/v1/users/locale'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_unauthenticated_requests_are_rejected(string $method, string $uri): void
    {
        $response = $this->json($method, $uri);

        $this->assertApiError($response, 401, 'unauthenticated');
    }

    #[DataProvider('protectedEndpoints')]
    public function test_requests_without_an_accept_header_still_get_json_401(string $method, string $uri): void
    {
        $response = $this->call($method, $uri);

        $this->assertApiError($response, 401, 'unauthenticated');
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $response = $this->withToken('dory_1|not-a-real-token')->getJson('/api/v1/auth/me');

        $this->assertApiError($response, 401, 'unauthenticated');
    }

    public function test_user_can_logout_and_the_revoked_token_no_longer_works(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->assertApiSuccess($this->withToken($token)->postJson('/api/v1/auth/logout'));
        $this->assertSame(0, $user->tokens()->count());

        $this->forgetAuthenticatedUser();

        $this->assertApiError(
            $this->withToken($token)->getJson('/api/v1/auth/me'),
            401,
            'unauthenticated',
        );
    }

    public function test_logout_revokes_only_the_current_device_token(): void
    {
        $user = User::factory()->create();
        $phoneToken = $this->tokenFor($user, 'phone');
        $tabletToken = $this->tokenFor($user, 'tablet');

        $this->withToken($phoneToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->forgetAuthenticatedUser();

        $this->withToken($tabletToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertSame(['tablet'], $user->tokens()->pluck('name')->all());
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old-phone', ['*'], now()->subMinute())->plainTextToken;

        $this->assertApiError(
            $this->withToken($token)->getJson('/api/v1/auth/me'),
            401,
            'unauthenticated',
        );
    }

    public function test_a_suspended_user_cannot_use_an_existing_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $user->forceFill(['status' => 'suspended'])->save();

        $this->assertApiError(
            $this->withToken($token)->getJson('/api/v1/auth/me'),
            403,
            'account_inactive',
        );
    }
}
