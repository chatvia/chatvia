<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * "Delete all" erases the conversations of the visitor's own chat session
 * only.
 */
it('leaves the conversations of another visitor alone', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent);

    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->deleteJson("/chat/{$agent->slug}/conversations")->assertOk();

    expect(Conversation::query()->firstWhere('external_id', $id)->trashed())->toBeFalse();
});
