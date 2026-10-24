<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds an enabled custom MCP server (added by URL, own `mcp_tools` rows, no
 * auth) in a new workspace unless `workspace_id` is given, {@see forOwner()} or
 * {@see attachedTo()}. The states {@see preset()} and {@see builtin()} build
 * catalog connectors that read their tools from the shared `connector_tools`
 * catalog.
 *
 * @extends Factory<AgentMcpServer>
 */
class AgentMcpServerFactory extends Factory
{
    #[\Override]
    protected $model = AgentMcpServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Short and lowercase: the slug is the `mcp__{slug}__` prefix of every
        // tool name, which LLM providers cap at 64 characters.
        $slug = 'srv_'.Str::lower(Str::random(8));

        return [
            // Lazy: only creates a workspace when the caller doesn't pass one.
            'workspace_id' => fn (): int => User::factory()->create()->workspace()->id,
            'category' => McpServerCategory::Custom,
            'connector_slug' => null,
            'uses_central_catalog' => false,
            'name' => Str::headline($slug),
            'description' => fake()->sentence(),
            'slug' => $slug,
            'url' => "https://mcp.example.test/{$slug}",
            'transport' => McpTransport::StreamableHttp,
            'auth_type' => McpAuthType::None,
            'is_enabled' => true,
        ];
    }

    /** A server of the given owner's (or teammate's) workspace. */
    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => ['workspace_id' => $user->workspace()->id]);
    }

    /** A server the workspace added by URL: its tools are its own `mcp_tools` rows. */
    public function custom(): static
    {
        return $this->state(fn (): array => [
            'category' => McpServerCategory::Custom,
            'connector_slug' => null,
            'uses_central_catalog' => false,
        ]);
    }

    /**
     * A catalog connector at its canonical URL (Notion, Stripe, …): its tools
     * come from the shared `connector_tools` catalog through `connector_tool_mcp_server`.
     */
    public function preset(string $connectorSlug = 'notion'): static
    {
        return $this->state(fn (): array => [
            'category' => McpServerCategory::Integration,
            'connector_slug' => $connectorSlug,
            'uses_central_catalog' => true,
            'name' => Str::headline($connectorSlug),
            'slug' => AgentMcpServer::makeSlug($connectorSlug),
            'url' => "https://mcp.{$connectorSlug}.example.test/mcp",
        ]);
    }

    /**
     * One of Chatvia's own servers (Charts, Forms, …) at its internal URL,
     * reading its tools from the shared catalog like a preset.
     */
    public function builtin(string $connectorSlug = 'charts'): static
    {
        return $this->state(fn (): array => [
            'category' => McpServerCategory::Builtin,
            'connector_slug' => $connectorSlug,
            'uses_central_catalog' => true,
            'name' => Str::headline($connectorSlug),
            'slug' => AgentMcpServer::makeSlug($connectorSlug),
            'url' => Str::rtrim((string) config('app.url'), '/').'/mcp/'.$connectorSlug,
        ]);
    }

    /** Switched off workspace-wide: it keeps its attachments but offers no tools. */
    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    /**
     * A server of the agent's workspace, attached to the agent through the
     * `agent_mcp_server` pivot.
     */
    public function attachedTo(Agent $agent, bool $alwaysAvailable = false): static
    {
        return $this
            ->state(fn (): array => ['workspace_id' => $agent->workspace_id])
            ->afterCreating(function (AgentMcpServer $server) use ($agent, $alwaysAvailable): void {
                $agent->mcpServers()->attach($server->id, ['always_available' => $alwaysAvailable]);
            });
    }
}
