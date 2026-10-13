<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Actions\Embedding;

use Domain\Form\Jobs\EmbedFormDefinitionJob;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Jobs\EmbedFunctionJob;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Illuminate\Support\Facades\DB;

/**
 * Embeds every tool again (connector tools, MCP tools, functions and forms)
 * after the embedding provider or model changed: vectors of two models
 * cannot be compared, so tool routing would match the wrong tools. The old
 * vectors are cleared first, then one embed job is queued per connector,
 * MCP server, function and form; the keyword (BM25) columns are kept.
 */
class ReembedToolVectorsAction
{
    public function execute(): void
    {
        DB::transaction(function (): void {
            ConnectorTool::query()->whereNotNull('embedding')->update(['embedding' => null]);
            McpTool::query()->whereNotNull('embedding')->update(['embedding' => null]);
            AgentFunction::query()->whereNotNull('embedding')->update(['embedding' => null]);
            FormDefinition::query()->whereNotNull('embedding')->update(['embedding' => null]);
        });

        ConnectorTool::query()->distinct()->pluck('connector_slug')
            ->each(fn (string $slug) => EmbedConnectorToolsJob::dispatch($slug));
        McpTool::query()->distinct()->pluck('mcp_server_id')
            ->each(fn (int $serverId) => EmbedMcpToolsJob::dispatch($serverId));
        AgentFunction::query()->pluck('id')
            ->each(fn (int $functionId) => EmbedFunctionJob::dispatch($functionId));
        FormDefinition::query()->pluck('id')
            ->each(fn (int $formId) => EmbedFormDefinitionJob::dispatch($formId));
    }
}
