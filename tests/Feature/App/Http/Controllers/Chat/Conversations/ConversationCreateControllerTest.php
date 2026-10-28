<?php

use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * A visitor's first message opens a conversation. It is capped like every
 * later message: the next turn sends it to the model as history, so an
 * oversized first message would get past the longest-message setting.
 */
it('opens a conversation from the first message', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    expect(HostedChatVisitor::startConversation($agent, 'Hello'))->toBeUuid();
});

it("refuses a first message longer than the install's longest-message setting", function (): void {
    config(['chatvia.guardrails.max_message_chars' => 20]);
    $agent = Agent::factory()->create(['is_public' => true]);

    $this->postJson("/chat/{$agent->slug}/conversations/create", ['message' => Str::repeat('a', 21)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});
