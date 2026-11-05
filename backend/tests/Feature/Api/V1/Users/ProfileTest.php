<?php

namespace Tests\Feature\Api\V1\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/v1/users/profile', [
            'name' => 'Rami Khoury',
            'phone' => '+961 70 123 456',
            'timezone' => 'Asia/Beirut',
        ]);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.name', 'Rami Khoury')
            ->assertJsonPath('data.phone', '+96170123456')
            ->assertJsonPath('data.timezone', 'Asia/Beirut');

        $user->refresh();
        $this->assertSame('Rami Khoury', $user->name);
        $this->assertSame('+96170123456', $user->phone);
    }

    public function test_profile_update_requires_at_least_one_field(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/v1/users/profile', []);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['profile']);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function forbiddenProfileFields(): array
    {
        return [
            'platform admin flag' => ['is_platform_admin', true],
            'account status' => ['status', 'active'],
            'email' => ['email', 'new@example.com'],
            'password' => ['password', 'Another-Pass-2026'],
            'verification date' => ['email_verified_at', '2026-01-01T00:00:00Z'],
            'public id' => ['public_id', '01JZZZZZZZZZZZZZZZZZZZZZZZ'],
        ];
    }

    #[DataProvider('forbiddenProfileFields')]
    public function test_user_cannot_modify_privileged_or_protected_fields(string $field, mixed $value): void
    {
        $user = User::factory()->create(['email' => 'rami@example.com']);
        $before = $user->only(['email', 'status', 'is_platform_admin', 'password', 'public_id', 'email_verified_at']);

        $response = $this->withToken($this->tokenFor($user))->putJson('/api/v1/users/profile', [
            'name' => 'Rami Khoury',
            $field => $value,
        ]);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        $user->refresh();
        $this->assertEquals($before, $user->only(array_keys($before)));
        $this->assertNotSame('Rami Khoury', $user->name, 'A rejected request must not partially apply.');
    }

    public function test_user_cannot_promote_themselves_to_platform_admin(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/profile', ['is_platform_admin' => 1])
            ->assertStatus(422);

        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_profile_update_only_affects_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Rami']);
        $other = User::factory()->create(['name' => 'Other Person']);

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/profile', ['name' => 'Rami Updated'])
            ->assertOk();

        $this->assertSame('Rami Updated', $user->fresh()->name);
        $this->assertSame('Other Person', $other->fresh()->name);
    }

    public function test_phone_number_must_be_unique(): void
    {
        User::factory()->create(['phone' => '+96170123456']);
        $user = User::factory()->create();

        $response = $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/profile', ['phone' => '+961 70 123 456']);

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_user_can_keep_their_own_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '+96170123456']);

        $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/profile', ['phone' => '+96170123456', 'name' => 'Same Phone'])
            ->assertOk();
    }

    public function test_user_can_update_their_language(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/locale', ['locale' => 'ar']);

        $this->assertApiSuccess($response)->assertJsonPath('data.locale', 'ar');
        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->withToken($this->tokenFor($user))
            ->putJson('/api/v1/users/locale', ['locale' => 'fr']);

        $this->assertApiError($response, 422, 'validation_failed')
            ->assertJsonValidationErrors(['locale']);
        $this->assertSame('en', $user->fresh()->locale);
    }
}
