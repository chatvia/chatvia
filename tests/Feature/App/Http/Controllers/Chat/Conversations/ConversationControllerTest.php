<?php

use Domain\Agent\Models\Agent;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The hosted chat lists the conversations of the visitor's chat session, by
 * the name they were given.
 */
it('lists the visitor\'s conversation once it is named', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent, 'Where is my order?');
    $this->patchJson("/chat/{$agent->slug}/conversations/{$id}", ['title' => 'Order status'])->assertOk();

    $this->getJson("/chat/{$agent->slug}/conversations")
        ->assertOk()
        ->assertJsonPath('0.id', $id)
        ->assertJsonPath('0.title', 'Order status');
});
