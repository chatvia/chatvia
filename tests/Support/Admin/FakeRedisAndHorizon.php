<?php

declare(strict_types=1);

namespace Tests\Support\Admin;

use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Mockery;

/**
 * Answers the Status page's Redis and queue checks (ServiceChecks) offline.
 * Redis answers a ping with its version, and Horizon has no supervisors and
 * no jobs, as on an install where it is not started. Without this, those
 * checks would talk to whatever Redis runs on the machine.
 */
final class FakeRedisAndHorizon
{
    public static function bind(): void
    {
        Redis::shouldReceive('connection')->andReturn(new class
        {
            /** @param  array<int, mixed>  $parameters */
            public function command(string $method, array $parameters = []): mixed
            {
                return $method === 'info'
                    ? ['redis_version' => '7.4.0', 'used_memory_human' => '1.00M', 'connected_clients' => '1', 'uptime_in_seconds' => '60']
                    : 'PONG';
            }
        });

        app()->instance(MasterSupervisorRepository::class, Mockery::mock(MasterSupervisorRepository::class, ['all' => [], 'names' => []]));
        app()->instance(SupervisorRepository::class, Mockery::mock(SupervisorRepository::class, ['all' => [], 'names' => []]));
        app()->instance(JobRepository::class, Mockery::mock(JobRepository::class, ['countPending' => 0, 'countRecentlyFailed' => 0]));
    }
}
