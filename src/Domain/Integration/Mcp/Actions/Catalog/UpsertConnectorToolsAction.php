<?php

namespace Domain\Integration\Mcp\Actions\Catalog;

use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writes a connector's tool definitions into the shared `connector_tools`
 * catalog — one row per (connector_slug, tool name), whichever workspace or
 * deploy step synced it. Used by the per-workspace catalog sync and by the
 * builtin sync that runs on deploy, so both store the same definition and
 * the same default permission.
 *
 * Embeddings stay null on insert (filled out of band) and are only cleared
 * again when the embedded text (the description) changes.
 */
class UpsertConnectorToolsAction
{
    public function __construct(
        private readonly ConnectorCatalog $catalog,
    ) {}

    /**
     * @param  list<McpToolDefinitionData>  $tools
     * @return Collection<int, ConnectorTool> The catalog rows, in the order given.
     */
    public function execute(string $connectorSlug, array $tools): Collection
    {
        // First-party builtins (forms, charts, leads) are safe, no-auth Chatvia
        // tools that never gate on approval, even their write tools — the same
        // rule the approval policy applies at run time.
        $builtin = $this->catalog->isBuiltinSlug($connectorSlug);

        return DB::transaction(fn (): Collection => new Collection(array_map(
            fn (McpToolDefinitionData $tool): ConnectorTool => $this->upsert(
                $connectorSlug,
                $tool,
                $builtin ? McpToolPermission::Allow : $tool->defaultPermission(),
            ),
            $tools,
        )));
    }

    /**
     * Race-safe by (connector_slug, name): two workspaces connecting the same
     * connector at once converge on one row. Scope and default permission are
     * facts about the tool and re-sync every time; a workspace's own choice
     * lives on its membership row.
     */
    private function upsert(string $connectorSlug, McpToolDefinitionData $tool, McpToolPermission $defaultPermission): ConnectorTool
    {
        $attributes = [
            'description' => $tool->description,
            'input_schema' => $tool->inputSchema,
            'annotations' => $tool->annotations,
            'scope' => $tool->scope(),
            'default_permission' => $defaultPermission,
            'fetched_at' => now(),
        ];

        $row = ConnectorTool::query()->firstOrCreate(
            ['connector_slug' => $connectorSlug, 'name' => $tool->name],
            $attributes,
        );

        if ($row->wasRecentlyCreated) {
            return $row;
        }

        if ($row->description !== $tool->description) {
            $row->embedding = null;
            $row->embedding_bm25 = null;
        }

        $row->fill($attributes);
        if ($row->isDirty()) {
            $row->save();
        }

        return $row;
    }
}
