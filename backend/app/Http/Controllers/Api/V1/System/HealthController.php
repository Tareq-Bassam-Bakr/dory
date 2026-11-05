<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Services\System\HealthCheckService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Liveness and dependency check for load balancers and monitoring.
 *
 * 200 with success=true when every check passes; 503 with success=false
 * otherwise. Per-check results are always in data.checks. Error details are
 * logged, never returned.
 */
class HealthController extends Controller
{
    public function __invoke(HealthCheckService $health): JsonResponse
    {
        $result = $health->run();
        $healthy = $result['healthy'];

        return ApiResponse::envelope(
            success: $healthy,
            message: $healthy ? __('api.health.ok') : __('api.health.unhealthy'),
            data: [
                'status' => $healthy ? 'ok' : 'unhealthy',
                'api_version' => 'v1',
                'timestamp' => now()->toIso8601String(),
                'checks' => $result['checks'],
            ],
            status: $healthy ? 200 : 503,
        );
    }
}
