<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\SourceRefData;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The hosted chat loads the sources of earlier answers from this endpoint
 * when a conversation opens. It returns them only while the agent shows
 * sources, so with the setting off even the remembered ones stay hidden.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->conversationId = HostedChatVisitor::startConversation($this->agent, 'How do I return an item?');
    $this->sources = "/chat/{$this->agent->slug}/conversations/{$this->conversationId}/sources";

    $this->source = new SourceRefData(id: 'chunk-11', label: 'Returns', kind: 'website', url: 'https://shop.test/returns', page: null, passages: 1);
    HostedChatVisitor::memory($this->agent, $this->conversationId)->storeSourcesForMessage('answer-1', [$this->source]);
});

it('hides the remembered sources while the agent does not show sources', function (): void {
    $this->getJson($this->sources)
        ->assertOk()
        ->assertExactJson([]);
});

it('returns the sources when the agent shows them', function (): void {
    $this->agent->setSetting('show_sources', true, 'appearance');

    $this->getJson($this->sources)
        ->assertOk()
        ->assertExactJson(['answer-1' => [$this->source->toArray()]]);
});
