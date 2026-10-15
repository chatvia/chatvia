<?php

use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;

/**
 * The hosted chat loads the sources of earlier answers from this endpoint
 * when a conversation opens. It returns them only while the agent shows
 * sources, so turning the setting off also hides the ones remembered before.
 */
beforeEach(function (): void {
    config(['chatvia.ai.query.rewriter.enabled' => false]);
    Bus::fake();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$this->agent->slug}";
    $this->conversationId = $this->postJson("{$this->base}/conversations/create", ['message' => 'How do I return an item?'])->json('id');

    $this->source = ['id' => 'chunk-11', 'label' => 'Returns', 'kind' => 'website', 'url' => 'https://shop.test/returns', 'page' => null, 'passages' => 1];
    app(ChatMemory::class)
        ->withScope(app(HostedChatSession::class)->scope($this->agent, $this->conversationId))
        ->storeSourcesForMessage('answer-1', [$this->source]);
});

it('hides the sources by default', function (): void {
    $this->getJson("{$this->base}/conversations/{$this->conversationId}/sources")
        ->assertOk()
        ->assertExactJson([]);
});

it('returns the sources when the agent shows them', function (): void {
    $this->agent->setSetting('show_sources', true, 'appearance');

    $this->getJson("{$this->base}/conversations/{$this->conversationId}/sources")
        ->assertOk()
        ->assertExactJson(['answer-1' => [$this->source]]);
});

it('hides sources remembered before the owner turned them off', function (): void {
    $this->agent->setSetting('show_sources', true, 'appearance');
    $this->agent->setSetting('show_sources', false, 'appearance');

    $this->getJson("{$this->base}/conversations/{$this->conversationId}/sources")
        ->assertOk()
        ->assertExactJson([]);
});
