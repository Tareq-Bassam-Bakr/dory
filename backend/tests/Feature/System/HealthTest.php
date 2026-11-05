<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * No RefreshDatabase here: the health check needs no tables, and one test
 * deliberately breaks the default connection.
 */
class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_healthy_dependencies(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.checks.cache', 'ok')
            ->assertJsonPath('data.checks.redis', 'not_used')
            ->assertJsonStructure(['data' => ['timestamp']]);
    }

    public function test_health_endpoint_returns_503_when_the_database_is_down(): void
    {
        config([
            'database.connections.broken' => [
                'driver' => 'sqlite',
                'database' => base_path('does-not-exist/missing.sqlite'),
                'prefix' => '',
            ],
            'database.default' => 'broken',
        ]);
        DB::purge('broken');

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', 'unhealthy')
            ->assertJsonPath('data.checks.database', 'fail');

        // The failure reason is logged, never returned.
        $this->assertStringNotContainsString('missing.sqlite', $response->getContent());
    }

    public function test_health_endpoint_reports_redis_honestly_when_configured_but_unavailable(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'database.redis.cache.port' => 1,
        ]);

        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.checks.redis', 'fail');
    }
}
