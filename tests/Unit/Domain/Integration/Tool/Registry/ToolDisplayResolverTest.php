<?php

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Tool\Enums\ToolKind;
use Domain\Integration\Tool\Registry\ToolDisplayResolver;
use Domain\Integration\Tool\ValueObjects\ToolName;

/**
 * The display resolver flags first-party builtins (forms, charts, leads) as
 * `is_builtin` so the stream layer can mark their tool events `internal` — the
 * widget + hosted chat suppress the "Calling …" indicator for them, since their
 * discovery/render is internal plumbing the visitor shouldn't see.
 */
it('flags a builtin connector tool as internal', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->builtin('forms')->create(['workspace_id' => $agent->workspace_id]);

    $display = app(ToolDisplayResolver::class)->resolve($agent, ToolName::parse('mcp__forms__render-form-tool'));

    expect($display->isBuiltin)->toBeTrue()
        ->and($display->kind)->toBe(ToolKind::Mcp);
});

it('does not flag an external connector tool as builtin', function (): void {
    $agent = Agent::factory()->create();
    AgentMcpServer::factory()->preset('stripe')->create(['workspace_id' => $agent->workspace_id]);

    $display = app(ToolDisplayResolver::class)->resolve($agent, ToolName::parse('mcp__stripe__search'));

    expect($display->isBuiltin)->toBeFalse();
});

it('never flags a webhook function as builtin', function (): void {
    $agent = Agent::factory()->create();

    $display = app(ToolDisplayResolver::class)->resolve($agent, ToolName::parse('some_custom_function'));

    expect($display->isBuiltin)->toBeFalse()
        ->and($display->kind)->toBe(ToolKind::Function);
});
