<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The hosted chat opens a conversation with the visitor's first message, in
 * the visitor's own chat session of the agent.
 */
it('opens a conversation of the agent from the first message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $id = HostedChatVisitor::startConversation($agent, 'Where is my order?');

    expect(Conversation::query()->firstWhere('external_id', $id)?->agent_id)->toBe($agent->id);
});
