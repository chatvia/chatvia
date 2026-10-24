<?php

declare(strict_types=1);

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Illuminate\Support\Facades\Queue;

/**
 * A custom server's own tools are embedded with the server's name and
 * description as context, so renaming or re-describing the server indexes
 * every tool again; any other change leaves the index alone. (Dropping the
 * agents' cached pools is tested on ToolRegistry.)
 */
beforeEach(function (): void {
    Queue::fake();
    $this->server = AgentMcpServer::factory()->create(['slug' => 'hejly-tutor']);
    McpTool::factory()->for($this->server, 'mcpServer')->create(['name' => 'ask-tutor']);
    McpTool::query()->where('mcp_server_id', $this->server->id)->update(['search_text' => 'indexed']);
});

it('re-indexes every tool when the server is renamed or re-described', function (array $change): void {
    $this->server->update($change);

    expect(McpTool::query()->where('mcp_server_id', $this->server->id)->value('search_text'))->toBeNull();
    Queue::assertPushed(EmbedMcpToolsJob::class, fn (EmbedMcpToolsJob $job): bool => $job->mcpServerId === $this->server->id);
})->with([
    'renamed' => [['name' => 'Hejly tutor']],
    're-described' => [['description' => 'Danish tutor for Hejly learners']],
]);

it('leaves the index alone on an unrelated change', function (): void {
    $this->server->update(['last_error' => null, 'catalog_cached_at' => now()]);

    expect(McpTool::query()->where('mcp_server_id', $this->server->id)->value('search_text'))->toBe('indexed');
    Queue::assertNothingPushed();
});
