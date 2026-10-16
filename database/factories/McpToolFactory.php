<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a tool of a custom MCP server (a new one unless `mcp_server_id` is
 * given or {@see for()} names one): a write tool that needs approval and has
 * no embeddings yet, as a catalog sync stores it.
 *
 * @extends Factory<McpTool>
 */
class McpToolFactory extends Factory
{
    #[\Override]
    protected $model = McpTool::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mcp_server_id' => AgentMcpServer::factory(),
            'name' => 'tool_'.Str::lower(Str::random(8)),
            'description' => fake()->sentence(),
            'input_schema' => ['type' => 'object', 'properties' => []],
            'annotations' => null,
            'scope' => McpToolScope::Write,
            'permission' => McpToolPermission::Approval,
            'embedding' => null,
            'search_text' => null,
            'fetched_at' => now(),
        ];
    }

    /** A read-only tool, which runs without approval. */
    public function read(): static
    {
        return $this->state(fn (): array => [
            'scope' => McpToolScope::Read,
            'permission' => McpToolPermission::Allow,
        ]);
    }

    /** A tool the workspace lets run without asking. */
    public function allowed(): static
    {
        return $this->state(fn (): array => ['permission' => McpToolPermission::Allow]);
    }

    /** A tool the workspace switched off: it never reaches an agent. */
    public function blocked(): static
    {
        return $this->state(fn (): array => ['permission' => McpToolPermission::Blocked]);
    }
}
