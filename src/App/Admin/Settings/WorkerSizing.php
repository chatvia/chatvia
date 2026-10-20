<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\WorkerCountsData;

/**
 * Recommends how many queue workers of each kind fit a server, for
 * `php artisan chatvia:workers:tune` and the installers. The numbers in
 * config/horizon.php fit a 4 GB server and are the floor; everything scales
 * up from there. The workers get a quarter of the machine's memory (the web
 * app, PostgreSQL and Redis share the rest), or most of the container's own
 * memory limit when one is set. The pools that work the processor hard
 * (ingestion, media) are also capped by processor count; the others mostly
 * wait on the model and the network.
 */
final readonly class WorkerSizing
{
    private const int GIB = 1024 ** 3;

    public function recommend(ServerResources $server): WorkerCountsData
    {
        // A container limit is this service's alone; host memory is shared.
        $budget = $server->memoryLimitBytes !== null
            ? $server->memoryLimitBytes * 0.8
            : $server->memoryBytes * 0.25;

        // 1 GiB of workers is the 4 GB reference server, where the
        // config/horizon.php numbers are right as they are.
        $factor = min(8.0, max(1.0, $budget / self::GIB));

        return new WorkerCountsData(
            chat: $this->clamp(6 * $factor, 6, 48),
            ingestion: $this->clamp(4 * $factor, 4, min(16, max(4, 2 * $server->cpus))),
            analytics: $this->clamp(2 * $factor, 2, 16),
            media: $this->clamp($factor / 2, 1, min(4, max(1, $server->cpus))),
            voice: $this->clamp(3 * $factor, 3, 24),
        );
    }

    private function clamp(float $value, int $min, int $max): int
    {
        return (int) min($max, max($min, round($value)));
    }
}
