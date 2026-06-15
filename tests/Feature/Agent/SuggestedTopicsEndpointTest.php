<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\ConversationTopic;

/**
 * The suggestions endpoint groups emergent (un-promoted) topic labels and
 * attaches example conversations. The examples used to be fetched with one
 * query per label (unbounded N+1); they now come from a single
 * window-function query — this pins the grouped shape and the per-label cap.
 */
function suggestedTopicsConversation(Agent $agent, string $title): Conversation
{
    $session = Session::create(['agent_id' => $agent->id]);

    return Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $session->agent_id,
        'source' => 'widget',
        'title' => $title,
    ]);
}

it('returns grouped suggestions with examples from a single batched query', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 2]);
    config(['chatvia.analytics.analysis.max_examples' => 2]);

    // Pro plan grants the `topics` capability the route is gated behind.
    $agent = proPlanAgent();
    $owner = $agent->user;

    // "refunds" appears in 3 conversations (above threshold, examples capped
    // at 2); "shipping" in 1 (below threshold — dropped).
    foreach (['Refund one', 'Refund two', 'Refund three'] as $title) {
        ConversationTopic::create([
            'chat_conversation_id' => suggestedTopicsConversation($agent, $title)->id,
            'suggested_label' => 'refunds',
        ]);
    }

    ConversationTopic::create([
        'chat_conversation_id' => suggestedTopicsConversation($agent, 'Shipping one')->id,
        'suggested_label' => 'shipping',
    ]);

    $response = $this->actingAs($owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($response)->toHaveCount(1)
        ->and($response[0]['label'])->toBe('refunds')
        ->and($response[0]['count'])->toBe(3)
        ->and($response[0]['examples'])->toHaveCount(2)
        ->and($response[0]['examples'][0])->toHaveKeys(['conversation_id', 'title']);

    // The `title` column is encrypted at rest; the examples query reads it via a
    // raw join that bypasses the model cast, so the controller must decrypt it.
    // Assert we get plaintext back, never the ciphertext blob (`eyJpdiI6...`).
    $titles = array_column($response[0]['examples'], 'title');

    expect($titles)->each->not->toStartWith('eyJ')
        ->and($titles)->toEqual(['Refund one', 'Refund two']);
});

it('does not leak another agent\'s suggestions or examples', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 1]);

    $agent = proPlanAgent();
    $owner = $agent->user;

    $other = User::factory()->create();
    $foreignAgent = createAgent($other);

    ConversationTopic::create([
        'chat_conversation_id' => suggestedTopicsConversation($foreignAgent, 'Foreign')->id,
        'suggested_label' => 'foreign-only',
    ]);

    $response = $this->actingAs($owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($response)->toBe([]);
});
