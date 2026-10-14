<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Jobs;

use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Adds Chatvia's own tools (charts, forms, the analytics tools) to the tool
 * catalog once the admin setup has an embedding service, so a new install
 * has them without `php artisan app:sync-builtin-mcp`. Safe to run again.
 */
class SyncBuiltinConnectorsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(SyncBuiltinConnectorsAction $sync): void
    {
        $sync->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));
    }
}
