<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolDisplayResolver;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * The display resolver flags first-party builtins (forms, charts, leads) as
 * `is_builtin` so the stream layer can mark their tool events `internal` — the
 * widget + hosted chat suppress the "Calling …" indicator for them, since their
 * discovery/render is internal plumbing the visitor shouldn't see.
 */
function displayServer(Agent $agent, string $slug, string $category): void
{
    AgentMcpServer::create([
        'user_id' => $agent->user_id,
        'connector_slug' => $slug,
        'category' => $category,
        'name' => ucfirst($slug),
        'slug' => $slug,
        'url' => 'https://mcp.example.test/'.$slug,
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
}

it('flags a builtin connector tool as internal', function (): void {
    $agent = Agent::factory()->create();
    displayServer($agent, 'forms', 'builtin');

    $display = app(ToolDisplayResolver::class)->resolve($agent, 'mcp__forms__render-form-tool');

    expect($display['is_builtin'])->toBeTrue()
        ->and($display['kind'])->toBe('mcp');
});

it('does not flag an external connector tool as builtin', function (): void {
    $agent = Agent::factory()->create();
    displayServer($agent, 'stripe', 'integration');

    $display = app(ToolDisplayResolver::class)->resolve($agent, 'mcp__stripe__search');

    expect($display['is_builtin'])->toBeFalse();
});

it('never flags a webhook function as builtin', function (): void {
    $agent = Agent::factory()->create();

    $display = app(ToolDisplayResolver::class)->resolve($agent, 'some_custom_function');

    expect($display['is_builtin'])->toBeFalse()
        ->and($display['kind'])->toBe('function');
});
