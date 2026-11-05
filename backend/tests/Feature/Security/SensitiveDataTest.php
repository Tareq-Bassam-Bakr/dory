<?php

namespace Tests\Feature\Security;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;
use Throwable;

/**
 * Secrets must not leak through logs or responses, even when requests fail.
 */
class SensitiveDataTest extends TestCase
{
    use BuildsTenants, RefreshDatabase;

    public function test_a_server_error_during_login_does_not_log_the_password(): void
    {
        $password = 'Pw9xLeakCheck';
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $exception = $event->context['exception'] ?? null;
            $logged[] = $event->message.($exception instanceof Throwable ? $exception->getTraceAsString() : '');
        });
        User::factory()->create(['email' => 'leak@example.com']);
        // Fails inside AuthService::login(), whose arguments include the password.
        User::retrieved(fn () => throw new RuntimeException('Simulated database failure'));

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'leak@example.com',
            'password' => $password,
            'device_name' => 'phpunit',
        ]);

        $this->assertApiError($response, 500, 'server_error');
        $log = implode("\n", $logged);
        $this->assertStringContainsString('AuthService->login', $log, 'The trace must include the frame that receives the password.');
        $this->assertStringNotContainsString($password, $log);
        $this->assertStringNotContainsString($password, $response->getContent());
    }

    public function test_server_errors_never_expose_traces_to_clients(): void
    {
        config(['app.debug' => true]);
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner);
        Branch::creating(fn () => throw new RuntimeException('internal detail'));

        $response = $this->actingAsUser($owner, 'POST', '/api/v1/businesses/'.$business->public_id.'/branches', ['name' => 'Main']);

        $this->assertApiError($response, 500, 'server_error');
        $this->assertArrayNotHasKey('trace', $response->json('debug') ?? []);

        config(['app.debug' => false]);
        $production = $this->actingAsUser($owner, 'POST', '/api/v1/businesses/'.$business->public_id.'/branches', ['name' => 'Main']);
        $this->assertArrayNotHasKey('debug', $production->json());
        $this->assertStringNotContainsString('internal detail', $production->getContent());
    }

    public function test_tenant_responses_never_contain_internal_numeric_ids(): void
    {
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner);
        $branch = $this->branchOf($business);
        $this->serviceOf($branch);

        foreach ([
            '/api/v1/businesses/manage',
            '/api/v1/businesses/'.$business->public_id,
            '/api/v1/businesses/'.$business->public_id.'/branches',
            '/api/v1/branches/'.$branch->public_id.'/services',
            '/api/v1/businesses/'.$business->public_id.'/members',
        ] as $uri) {
            $json = $this->actingAsUser($owner, 'GET', $uri)->assertOk()->json('data');
            array_walk_recursive($json, function (mixed $value, string|int $key) use ($uri): void {
                if (in_array($key, ['id', 'business_id', 'branch_id', 'user_id', 'category_id', 'created_by'], true)) {
                    $this->assertIsString($value, "{$uri} exposes a numeric {$key}.");
                    $this->assertSame(26, strlen($value), "{$uri} exposes a non-ULID {$key}.");
                }
            });
        }
    }
}
