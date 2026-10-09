<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedMcpToolsAction;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Tests\Support\FakeEmbeddingsProvider;

function embedTestServer(string $slug): AgentMcpServer
{
    return AgentMcpServer::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Acme CRM',
        'description' => 'Customer records',
        'slug' => $slug,
        'url' => "https://mcp.example.test/{$slug}",
        'transport' => 'streamable_http',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

function embedTestMcpTool(AgentMcpServer $server, string $name): McpTool
{
    return McpTool::create([
        'mcp_server_id' => $server->id,
        'name' => $name,
        'description' => "Does {$name}",
        'input_schema' => ['type' => 'object', 'properties' => []],
        'permission' => McpToolPermission::Allow,
        'fetched_at' => now(),
    ]);
}

function mcpToolIsEmbedded(McpTool $tool): bool
{
    $row = McpTool::query()
        ->whereKey($tool->id)
        ->selectRaw('embedding IS NOT NULL AND embedding_bm25 IS NOT NULL AS embedded')
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

    $texts = [];
    $recording = Mockery::mock(EmbeddingsProviderInterface::class);
    $recording->shouldReceive('embed')->andReturnUsing(function (string $text) use (&$texts): array {
        $texts[] = $text;

        return (new FakeEmbeddingsProvider)->embed($text);
    });
    app()->instance(EmbeddingsProviderInterface::class, $recording);

    app(EmbedMcpToolsAction::class)->execute($server->id);

    expect($texts)->toBe(["Acme CRM\nCustomer records\n\nlookup_customer\n\nDoes lookup_customer"]);
});
