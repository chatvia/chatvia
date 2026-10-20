<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Jobs;

use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;

/**
 * Adds Chatvia's own tools (charts, forms, the analytics tools) to the tool
 * catalog once the admin setup has an embedding service, so a new install
 * has them without `php artisan chatvia:mcp:sync-builtin`. Safe to run again.
 */
// Safe to run again, so a passing failure gets two more tries.
#[Tries(3)]
class SyncBuiltinConnectorsJob implements ShouldQueue
{
    use Queueable;

    public function handle(SyncBuiltinConnectorsAction $sync): void
    {
        $sync->execute(new SyncBuiltinConnectorsData(queueEmbedding: true));
    }
}
