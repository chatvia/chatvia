<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * One line of the tool-call audit log: a new agent ran its `get_weather`
 * function on its own (no approval asked), with no error. The agent's name
 * and workspace are copied, as the app stores them.
 *
 * @extends Factory<AgentActivity>
 */
class AgentActivityFactory extends Factory
{
    #[\Override]
    protected $model = AgentActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'agent_name' => fn (array $attributes): string => (string) Agent::query()->whereKey($attributes['agent_id'])->value('name'),
            'workspace_id' => fn (array $attributes): int => (int) Agent::query()->whereKey($attributes['agent_id'])->value('workspace_id'),
            'trace_id' => fn (): string => (string) Str::uuid(),
            'tool_call_id' => fn (): string => 'call_'.Str::random(6),
            'tool_name' => 'get_weather',
            'tool_kind' => 'function',
            'outcome' => 'auto',
            'is_gated' => false,
            'is_error' => false,
            'arguments_truncated' => false,
            'result_truncated' => false,
        ];
    }

    public function forAgent(Agent $agent): static
    {
        return $this->state(['agent_id' => $agent->id]);
    }

    /** A tool of a connector (an MCP server) instead of a function. */
    public function ofConnector(string $slug, string $name): static
    {
        return $this->state(['tool_kind' => 'mcp', 'connector_slug' => $slug, 'connector_name' => $name]);
    }
}
