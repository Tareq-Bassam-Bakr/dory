<?php

namespace Tests;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Issues a real Sanctum token, as the login endpoint would.
     */
    protected function tokenFor(User $user, string $deviceName = 'phpunit'): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }

    /**
     * The password every factory-made user has (random per test run).
     */
    protected function factoryPassword(): string
    {
        return UserFactory::plainPassword();
    }

    /**
     * Auth guards cache the resolved user for the lifetime of the application
     * instance, which spans several requests in one test. Call this between
     * requests that must authenticate independently (e.g. after logout).
     */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Asserts the standard error envelope (docs/API_CONVENTIONS.md).
     */
    protected function assertApiError(TestResponse $response, int $status, string $code): TestResponse
    {
        return $response
            ->assertStatus($status)
            ->assertJsonStructure(['success', 'message', 'code', 'errors'])
            ->assertJson(['success' => false, 'code' => $code]);
    }

    /**
     * Asserts the standard success envelope (docs/API_CONVENTIONS.md).
     */
    protected function assertApiSuccess(TestResponse $response, int $status = 200): TestResponse
    {
        return $response
            ->assertStatus($status)
            ->assertJsonStructure(['success', 'message', 'data', 'meta'])
            ->assertJson(['success' => true]);
    }
}
