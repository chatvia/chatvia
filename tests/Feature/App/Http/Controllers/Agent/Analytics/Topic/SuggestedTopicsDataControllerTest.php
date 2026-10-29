<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * The suggestions endpoint groups emergent (un-promoted) topic labels above
 * the occurrence threshold, caps their example conversations, decrypts the
 * example titles, and never shows another agent's suggestions.
 */
beforeEach(fn () => withLicense());

function stdConversation(Agent $agent, string $title): Conversation
{
    return Conversation::factory()->withTitle($title)->create(['agent_id' => $agent->id, 'source' => ConversationSource::Widget]);
}

it('groups the labels above the threshold with capped, decrypted examples', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 2, 'chatvia.analytics.analysis.max_examples' => 2]);
    $agent = Agent::factory()->create();
    foreach (['Refund one', 'Refund two', 'Refund three'] as $title) {
        ConversationTopic::factory()->forConversation(stdConversation($agent, $title))->suggested('refunds')->create();
    }
    ConversationTopic::factory()->forConversation(stdConversation($agent, 'Shipping one'))->suggested('shipping')->create();

    $suggestions = $this->actingAs($agent->workspace->owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['label'])->toBe('refunds')
        ->and($suggestions[0]['count'])->toBe(3)
        ->and(array_column($suggestions[0]['examples'], 'title'))->toEqual(['Refund one', 'Refund two']);
});

it('does not leak the suggestions of another agent in the same workspace', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 1]);
    $agent = Agent::factory()->create();
    $sibling = createAgent($agent->workspace->owner, ['name' => 'Sibling']);
    ConversationTopic::factory()->forConversation(stdConversation($sibling, 'Sibling chat'))->suggested('sibling-only')->create();

    $suggestions = $this->actingAs($agent->workspace->owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($suggestions)->toBe([]);
});
