<?php

namespace App\Services\System;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Checks the infrastructure the API depends on.
 *
 * Failure details go to the log (with the request id), never to the public
 * response.
 */
final class HealthCheckService
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAIL = 'fail';

    /** Redis is not configured for any store, so it was not checked. */
    public const STATUS_NOT_USED = 'not_used';

    /**
     * @return array{healthy: bool, checks: array<string, string>}
     */
    public function run(): array
    {
        $checks = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'cache' => $this->check('cache', function (): void {
                $key = 'health:'.Str::random(12);
                Cache::put($key, true, 10);
                throw_unless(Cache::pull($key) === true, new RuntimeException('Cache read-back failed.'));
            }),
            'redis' => $this->redisIsUsed()
                ? $this->check('redis', fn () => Redis::connection()->ping())
                : self::STATUS_NOT_USED,
        ];

        return [
            'healthy' => ! in_array(self::STATUS_FAIL, $checks, true),
            'checks' => $checks,
        ];
    }

    /**
     * True when the cache, queue or session is configured to use Redis.
     */
    public function redisIsUsed(): bool
    {
        return in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);
    }

    private function check(string $name, callable $probe): string
    {
        try {
            $probe();

            return self::STATUS_OK;
        } catch (Throwable $e) {
            Log::error("Health check failed: {$name}", ['exception' => $e]);

            return self::STATUS_FAIL;
        }
    }
}
