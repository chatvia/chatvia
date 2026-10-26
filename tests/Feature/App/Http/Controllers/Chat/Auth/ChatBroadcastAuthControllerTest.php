<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * The hosted chat asks for permission to listen for its generated images and
 * audio under its own session: granted for the visitor's own conversation,
 * refused for anyone else's or for any other channel.
 */
it('lets the visitor listen on their own conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent);

    $this->postJson("/chat/{$agent->slug}/broadcasting/auth", ['channel_name' => 'private-chat-media.'.$id, 'socket_id' => '1234.5678'])
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('refuses another visitor\'s conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    HostedChatVisitor::startConversation($agent);
    $theirs = Conversation::factory()->create(['agent_id' => $agent->id]);

    $this->postJson("/chat/{$agent->slug}/broadcasting/auth", ['channel_name' => 'private-chat-media.'.$theirs->external_id, 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

it('refuses a visitor with no chat yet', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    $this->postJson("/chat/{$agent->slug}/broadcasting/auth", ['channel_name' => 'private-chat-media.'.$conversation->external_id, 'socket_id' => '1234.5678'])
        ->assertForbidden();
});

it('asks for the channel and the connection id', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    $this->postJson("/chat/{$agent->slug}/broadcasting/auth", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel_name', 'socket_id']);
});
