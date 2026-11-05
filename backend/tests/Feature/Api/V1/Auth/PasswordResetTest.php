<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-New-Pass-2026';

    public function test_forgot_password_sends_a_reset_link_to_an_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'lina@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'LINA@example.com']);

        $this->assertApiSuccess($response);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_answers_identically_for_an_unknown_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'lina@example.com']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'lina@example.com']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@example.com']);

        $this->assertApiSuccess($unknown);
        $this->assertSame($known->json('message'), $unknown->json('message'));
        Notification::assertSentTimes(ResetPassword::class, 1);
    }

    public function test_forgot_password_validates_the_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nope']);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['email']);
    }

    public function test_reset_link_points_to_the_configured_client_url(): void
    {
        Notification::fake();
        config(['dory.password_reset_url' => 'dory://reset-password']);
        $user = User::factory()->create(['email' => 'lina@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'lina@example.com'])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'dory://reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email='.urlencode('lina@example.com'));
        });
    }

    public function test_user_can_reset_the_password_and_all_tokens_are_revoked(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'lina@example.com']);
        $this->tokenFor($user, 'phone');
        $this->tokenFor($user, 'tablet');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'lina@example.com'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'lina@example.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertApiSuccess($response);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        $user = User::factory()->create(['email' => 'lina@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'forged-token',
            'email' => 'lina@example.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['token']);
        $this->assertTrue(Hash::check($this->factoryPassword(), $user->fresh()->password));
    }

    public function test_reset_password_gives_unknown_emails_the_same_error_as_bad_tokens(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'forged-token',
            'email' => 'ghost@example.com',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['token'])->assertJsonMissingValidationErrors(['email']);
    }

    public function test_reset_password_validates_input(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'lina@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['token', 'password']);
    }
}
