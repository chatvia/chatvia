<?php

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Tests\Support\FakeEmbeddingsProvider;

function embedTestServer(string $slug): AgentMcpServer
{
    return AgentMcpServer::factory()->create(['name' => 'Acme CRM', 'description' => 'Customer records', 'slug' => $slug]);
}

function embedTestMcpTool(AgentMcpServer $server, string $name): McpTool
{
    return McpTool::factory()->for($server, 'mcpServer')->allowed()->create(['name' => $name, 'description' => "Does {$name}"]);
}

function mcpToolIsEmbedded(McpTool $tool): bool
{
    $row = McpTool::query()
        ->whereKey($tool->id)
        ->selectRaw('embedding IS NOT NULL AND search_text IS NOT NULL AS embedded')
        ->first();

    return (bool) $row->embedded;
}

it('embeds the unembedded tools of one custom server only', function (): void {
    $server = embedTestServer('acme');
    $lookup = embedTestMcpTool($server, 'lookup_customer');
    $update = embedTestMcpTool($server, 'update_customer');
    $otherServerTool = embedTestMcpTool(embedTestServer('other'), 'search');

    app(EmbedMcpToolsAction::class)->execute($server->id);

    expect(mcpToolIsEmbedded($lookup))->toBeTrue()
        ->and(mcpToolIsEmbedded($update))->toBeTrue()
        ->and(mcpToolIsEmbedded($otherServerTool))->toBeFalse();
});

it('embeds the server name and description alongside each tool', function (): void {
    $server = embedTestServer('acme');
    embedTestMcpTool($server, 'lookup_customer');

    $embedder = new FakeEmbeddingsProvider;
    app()->instance(EmbeddingsProviderInterface::class, $embedder);

    app(EmbedMcpToolsAction::class)->execute($server->id);

    expect($embedder->texts)->toBe(["Acme CRM\nCustomer records\n\nlookup_customer\n\nDoes lookup_customer"]);
});
