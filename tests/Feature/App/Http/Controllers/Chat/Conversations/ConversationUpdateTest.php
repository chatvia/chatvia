<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The visitor renames a conversation of their own chat session; another
 * visitor's conversation is not found.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->id = HostedChatVisitor::startConversation($this->agent);
    $this->url = "/chat/{$this->agent->slug}/conversations/{$this->id}";
});

it('renames the visitor\'s conversation', function (): void {
    $this->patchJson($this->url, ['title' => 'Order status'])->assertOk();

    expect(Conversation::query()->firstWhere('external_id', $this->id)->title)->toBe('Order status');
});

it('requires a title to rename a conversation', function (): void {
    $this->patchJson($this->url, [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('answers 404 for a conversation of another visitor and keeps its name', function (): void {
    $name = Conversation::query()->firstWhere('external_id', $this->id)->title;
    // A fresh browser session is a different visitor.
    $this->flushSession();

    $this->patchJson($this->url, ['title' => 'Hijacked'])->assertNotFound();

    expect(Conversation::query()->firstWhere('external_id', $this->id)->title)->toBe($name);
});
