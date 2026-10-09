<?php

namespace App\Console\Commands;

use Domain\Integration\Mcp\Actions\Catalog\SyncBuiltinConnectorsAction;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deploy step that catalogs Chatvia's built-in MCP tools and reconciles every
 * workspace's tool memberships ({@see SyncBuiltinConnectorsAction}). Run after
 * app:migrate; idempotent.
 */
#[Description("Catalog + embed Chatvia's built-in MCP tools into the shared connector_tools catalog and reconcile every workspace's tool memberships.")]
#[Signature('app:sync-builtin-mcp
        {--queue : Defer embedding to the queue (EmbedConnectorToolsJob) instead of running it inline}
        {--fresh : Re-embed every tool, not only ones whose text changed}')]
class SyncBuiltinMcpRegistry extends Command
{
    public function handle(SyncBuiltinConnectorsAction $sync): int
    {
        $result = $sync->execute(new SyncBuiltinConnectorsData(
            queueEmbedding: (bool) $this->option('queue'),
            reembedAll: (bool) $this->option('fresh'),
        ));

        foreach ($result->connectors as $connector) {
            $this->line(sprintf('  %-18s (%s): %d tools — indexed + reconciled', $connector->slug, $connector->offeredAs, $connector->toolsCount));
        }

        $this->info("Built-in MCP registry synced. Flushed tool-pool cache for {$result->flushedAgents} agent(s).");

        return self::SUCCESS;
    }
}
