<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Services\Business\BusinessMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/auth/login';

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'omar@example.com']);

        $response = $this->postJson(self::URL, [
            'email' => 'omar@example.com',
            'password' => $this->factoryPassword(),
            'device_name' => 'iPhone 15',
        ]);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.token.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token' => ['access_token', 'expires_at']]]);

        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_login_returns_the_users_memberships_like_me_does(): void
    {
        $user = User::factory()->create();
        $business = app(BusinessMembershipService::class)
            ->createBusinessWithOwner($user, 'Shifa Clinic');

        $response = $this->postJson(self::URL, [
            'email' => $user->email,
            'password' => $this->factoryPassword(),
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.memberships.0.role', 'owner')
            ->assertJsonPath('data.user.memberships.0.business.id', $business->public_id);
    }

    public function test_login_email_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'omar@example.com']);

        $response = $this->postJson(self::URL, [
            'email' => ' Omar@Example.COM ',
            'password' => $this->factoryPassword(),
            'device_name' => 'iPhone 15',
        ]);

        $response->assertOk();
    }

    public function test_login_fails_with_an_incorrect_password(): void
    {
        User::factory()->create(['email' => 'omar@example.com']);

        $response = $this->postJson(self::URL, [
            'email' => 'omar@example.com',
            'password' => 'Wrong-Password-1',
            'device_name' => 'iPhone 15',
        ]);

        $this->assertApiError($response, 401, 'invalid_credentials');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_unknown_email_gets_the_same_response_as_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'omar@example.com']);

        $wrongPassword = $this->postJson(self::URL, [
            'email' => 'omar@example.com',
            'password' => 'Wrong-Password-1',
            'device_name' => 'iPhone 15',
        ]);
        $unknownEmail = $this->postJson(self::URL, [
            'email' => 'nobody@example.com',
            'password' => 'Wrong-Password-1',
            'device_name' => 'iPhone 15',
        ]);

        $this->assertApiError($unknownEmail, 401, 'invalid_credentials');
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->suspended()->create(['email' => 'omar@example.com']);

        $response = $this->postJson(self::URL, [
            'email' => 'omar@example.com',
            'password' => $this->factoryPassword(),
            'device_name' => 'iPhone 15',
        ]);

        $this->assertApiError($response, 403, 'account_inactive');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_login_validates_input(): void
    {
        $response = $this->postJson(self::URL, ['email' => 'not-an-email']);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_login_is_rate_limited_per_account_and_ip(): void
    {
        User::factory()->create(['email' => 'omar@example.com']);
        $payload = [
            'email' => 'omar@example.com',
            'password' => 'Wrong-Password-1',
            'device_name' => 'iPhone 15',
        ];

        for ($attempt = 1; $attempt <= config('dory.rate_limits.auth'); $attempt++) {
            $this->postJson(self::URL, $payload)->assertStatus(401);
        }

        $response = $this->postJson(self::URL, $payload);

        $this->assertApiError($response, 429, 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}
