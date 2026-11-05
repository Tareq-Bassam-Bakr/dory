<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ApiConventionsTest extends TestCase
{
    public function test_success_responses_use_the_standard_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertSame(['success', 'message', 'data', 'meta'], array_keys($response->json()));
    }

    public function test_unknown_api_routes_return_a_json_404_envelope(): void
    {
        // No Accept header: API routes must answer in JSON regardless.
        $response = $this->get('/api/v1/does-not-exist');

        $this->assertApiError($response, 404, 'not_found');
    }

    public function test_routes_are_only_served_under_the_v1_prefix(): void
    {
        $this->assertApiError($this->postJson('/api/auth/login'), 404, 'not_found');
    }

    public function test_wrong_http_method_returns_405_envelope(): void
    {
        $this->assertApiError($this->getJson('/api/v1/auth/login'), 405, 'method_not_allowed');
    }

    public function test_every_response_carries_a_generated_request_id(): void
    {
        $response = $this->getJson('/api/v1/health');

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    public function test_a_well_formed_incoming_request_id_is_propagated(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => 'gateway-trace-123456'])->getJson('/api/v1/health');

        $response->assertHeader('X-Request-Id', 'gateway-trace-123456');
    }

    public function test_a_malformed_incoming_request_id_is_replaced(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => str_repeat('x', 200)])->getJson('/api/v1/health');

        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-Id')));
    }

    public function test_error_responses_also_carry_a_request_id(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_security_headers_are_set(): void
    {
        $this->getJson('/api/v1/health')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_server_errors_hide_internal_details_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        $this->registerFailingRoute();

        $response = $this->getJson('/api/v1/test-probe/explode');

        $this->assertApiError($response, 500, 'server_error')
            ->assertJsonMissingPath('debug');
        $this->assertStringNotContainsString('internal detail that must not leak', $response->getContent());
        $this->assertStringNotContainsString('trace', $response->getContent());
    }

    public function test_server_errors_include_limited_details_in_debug_mode(): void
    {
        config(['app.debug' => true]);
        $this->registerFailingRoute();

        $response = $this->getJson('/api/v1/test-probe/explode');

        $this->assertApiError($response, 500, 'server_error')
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonMissingPath('debug.trace');
    }

    private function registerFailingRoute(): void
    {
        Route::middleware('api')->get('api/v1/test-probe/explode', function (): never {
            throw new RuntimeException('internal detail that must not leak');
        });
    }
}
