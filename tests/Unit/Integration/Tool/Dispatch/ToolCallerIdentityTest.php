<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Tool\Dispatch\ToolCallerIdentity;

it('maps the agent and owner external ids to identity headers', function (): void {
    $user = new User;
    $user->external_id = 'user-uuid';

    $agent = new Agent;
    $agent->external_id = 'agent-uuid';
    $agent->setRelation('user', $user);

    expect(ToolCallerIdentity::headers($agent))->toBe([
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-User-Id' => 'user-uuid',
        'X-Chatvia-Metadata' => 'null',
    ]);
});

it('stamps the conversation and the signed-in chatter when known', function (): void {
    $user = new User;
    $user->external_id = 'user-uuid';

    $agent = new Agent;
    $agent->external_id = 'agent-uuid';
    $agent->setRelation('user', $user);

    expect(ToolCallerIdentity::headers($agent, 'conv-uuid', 'chatter-uuid'))->toBe([
        'X-Chatvia-Agent-Id' => 'agent-uuid',
        'X-Chatvia-User-Id' => 'user-uuid',
        'X-Chatvia-Metadata' => 'null',
        'X-Chatvia-Conversation-Id' => 'conv-uuid',
        'X-Chatvia-Chatter-Id' => 'chatter-uuid',
    ])->and(ToolCallerIdentity::headers($agent))->not->toHaveKeys(['X-Chatvia-Conversation-Id', 'X-Chatvia-Chatter-Id']);
});
