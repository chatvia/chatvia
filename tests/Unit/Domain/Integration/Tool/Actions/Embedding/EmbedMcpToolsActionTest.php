<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Tests\Support\Integration\Tool\ToolVectors;

/**
 * Embedding a custom server's own tools: only that server's tools that have
 * no vectors yet.
 */
function emtServer(string $slug): AgentMcpServer
{
    return AgentMcpServer::factory()->create(['name' => 'Acme CRM', 'description' => 'Customer records', 'slug' => $slug]);
}

function emtTool(AgentMcpServer $server, string $name): McpTool
{
    return McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => $name, 'description' => "Does {$name}"]);
}

it('embeds the unembedded tools of one custom server only', function (): void {
    $server = emtServer('acme');
    $lookup = emtTool($server, 'lookup_customer');
    $update = emtTool($server, 'update_customer');
    $otherServerTool = emtTool(emtServer('other'), 'search');

    app(EmbedMcpToolsAction::class)->execute($server->id);

    expect(ToolVectors::embedded($lookup))->toBeTrue()
        ->and(ToolVectors::embedded($update))->toBeTrue()
        ->and(ToolVectors::embedded($otherServerTool))->toBeFalse();
});
