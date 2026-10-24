<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;

/**
 * Every outgoing tool call (MCP server or webhook function) says who is
 * calling in the X-Chatvia-* headers: the agent and its workspace always,
 * the conversation and the signed-in chatter when they are known.
 */
it('maps the agent and workspace external ids to identity headers', function (): void {
    $workspace = Workspace::factory()->create();

    $agent = new Agent;
    $agent->external_id = 'agent-uuid';
    $agent->workspace_id = $workspace->id;

    expect(ToolCallerIdentity::headers($agent))->toBe([
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-Workspace-Id' => $workspace->external_id,
        'X-Chatvia-Metadata' => 'null',
    ]);
});

it('stamps the conversation and the signed-in chatter when known', function (): void {
    $workspace = Workspace::factory()->create();

    $agent = new Agent;
    $agent->external_id = 'agent-uuid';
    $agent->workspace_id = $workspace->id;

    expect(ToolCallerIdentity::headers($agent, 'conv-uuid', 'chatter-uuid'))->toBe([
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-Workspace-Id' => $workspace->external_id,
        'X-Chatvia-Metadata' => 'null',
        'X-Chatvia-Conversation-Id' => 'conv-uuid',
        'X-Chatvia-Chatter-Id' => 'chatter-uuid',
    ])->and(ToolCallerIdentity::headers($agent))->not->toHaveKeys(['X-Chatvia-Conversation-Id', 'X-Chatvia-Chatter-Id']);
});
