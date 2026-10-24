<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * Dismissing a topic suggestion of the agent's conversations drops it without
 * making a topic. That other agents keep their identical label is in
 * DismissSuggestedTopicActionTest.
 */
it('dismisses a suggestion of the agent without making a topic', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $suggestion = ConversationTopic::factory()->forConversation(Conversation::factory()->create(['agent_id' => $agent->id]))->suggested('refunds')->create();

    $this->actingAs($owner)
        ->deleteJson(route('agents.topics.suggestions.dismiss', $agent), ['label' => 'refunds'])
        ->assertOk();

    expect(ConversationTopic::query()->whereKey($suggestion->id)->exists())->toBeFalse()
        ->and($agent->topics()->exists())->toBeFalse();
});
