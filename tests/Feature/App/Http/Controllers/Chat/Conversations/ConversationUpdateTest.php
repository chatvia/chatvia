<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * Renaming a hosted-chat conversation: another visitor's conversation is not
 * found and keeps its name.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->id = HostedChatVisitor::startConversation($this->agent);
    $this->url = "/chat/{$this->agent->slug}/conversations/{$this->id}";
});

it('answers 404 for a conversation of another visitor and keeps its name', function (): void {
    $name = Conversation::query()->firstWhere('external_id', $this->id)->title;
    $this->flushSession();

    $this->patchJson($this->url, ['title' => 'Hijacked'])->assertNotFound();

    expect(Conversation::query()->firstWhere('external_id', $this->id)->title)->toBe($name);
});
