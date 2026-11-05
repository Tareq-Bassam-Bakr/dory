<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/auth/register';

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sara Haddad',
            'email' => 'sara@example.com',
            'password' => 'Queue-Ready-2026',
            'password_confirmation' => 'Queue-Ready-2026',
            'device_name' => 'Pixel 8',
        ], $overrides);
    }

    public function test_user_can_register_and_receives_a_token(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson(self::URL, $this->validPayload([
            'phone' => '+963 912-345-678',
            'locale' => 'ar',
            'timezone' => 'Asia/Damascus',
        ]));

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonPath('data.user.phone', '+963912345678')
            ->assertJsonPath('data.user.locale', 'ar')
            ->assertJsonPath('data.user.timezone', 'Asia/Damascus')
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.user.is_platform_admin', false)
            ->assertJsonPath('data.token.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $user = User::query()->sole();
        $this->assertTrue(Hash::check('Queue-Ready-2026', $user->password));
        $this->assertFalse($user->is_platform_admin);

        // The public id is a ULID, never the internal numeric id.
        $publicId = $response->json('data.user.id');
        $this->assertTrue(Str::isUlid($publicId));
        $this->assertSame($user->public_id, $publicId);

        // Tokens carry the secret-scanning prefix and are stored only as a hash.
        // Sanctum format: "{token id}|{prefix}{secret}".
        $plainToken = $response->json('data.token.access_token');
        [, $secret] = explode('|', $plainToken, 2);
        $this->assertStringStartsWith(config('sanctum.token_prefix'), $secret);
        $stored = PersonalAccessToken::query()->sole();
        $this->assertSame('Pixel 8', $stored->name);
        $this->assertSame(hash('sha256', $secret), $stored->token);
        $this->assertNotNull($stored->expires_at);

        Event::assertDispatched(Registered::class);
    }

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->postJson(self::URL, []);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]));

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload([
            'password_confirmation' => 'Different-Pass-2026',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_registration_rejects_a_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);

        $response = $this->postJson(self::URL, $this->validPayload(['email' => '  SARA@Example.com ']));

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_an_invalid_phone_number(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload(['phone' => '0912345678']));

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_registration_rejects_an_unsupported_locale(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload(['locale' => 'fr']));

        $response->assertStatus(422)->assertJsonValidationErrors(['locale']);
    }

    public function test_registration_cannot_grant_platform_admin_privileges(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload(['is_platform_admin' => true]));

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['is_platform_admin']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_cannot_set_account_status(): void
    {
        $response = $this->postJson(self::URL, $this->validPayload(['status' => 'active']));

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->assertDatabaseCount('users', 0);
    }
}
