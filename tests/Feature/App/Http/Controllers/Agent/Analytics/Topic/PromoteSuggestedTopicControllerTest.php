<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * Promoting a topic suggestion of the agent's conversations makes it a topic
 * of the agent. Which conversation rows it converts is in
 * PromoteSuggestedTopicActionTest.
 */
// Topic analytics need a license key; EnsureAnalyticsLicenseTest covers the gate.
beforeEach(fn () => withLicense());

it('promotes a suggestion to a topic of the agent', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    ConversationTopic::factory()->forConversation(Conversation::factory()->create(['agent_id' => $agent->id]))->suggested('refunds')->create();

    $this->actingAs($owner)
        ->postJson(route('agents.topics.suggestions.promote', $agent), ['label' => 'refunds'])
        ->assertOk()
        ->assertJsonPath('topic.topic', 'refunds');

    expect($agent->topics()->where('topics.topic', 'refunds')->exists())->toBeTrue();
});
