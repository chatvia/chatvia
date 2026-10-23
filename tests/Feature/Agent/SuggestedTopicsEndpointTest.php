<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The suggestions endpoint groups emergent (un-promoted) topic labels and
 * attaches example conversations: the grouped shape, the per-label cap, and
 * one query for the examples of every label.
 */
function suggestedTopicsConversation(Agent $agent, string $title): Conversation
{
    return Conversation::factory()->withTitle($title)->create(['agent_id' => $agent->id, 'source' => ConversationSource::Widget]);
}

it('returns grouped suggestions with capped examples', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 2]);
    config(['chatvia.analytics.analysis.max_examples' => 2]);

    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    // "refunds" appears in 3 conversations (above threshold, examples capped
    // at 2); "shipping" in 1 (below threshold — dropped).
    foreach (['Refund one', 'Refund two', 'Refund three'] as $title) {
        ConversationTopic::create([
            'workspace_id' => $agent->workspace_id,
            'chat_conversation_id' => suggestedTopicsConversation($agent, $title)->id,
            'suggested_label' => 'refunds',
        ]);
    }

    ConversationTopic::create([
        'workspace_id' => $agent->workspace_id,
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

it('does not leak the suggestions or examples of another agent in the same workspace', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 1]);

    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    // A sibling shares the workspace, so only the agent filter keeps it out.
    $sibling = createAgent($owner, ['name' => 'Sibling']);

    ConversationTopic::create([
        'workspace_id' => $sibling->workspace_id,
        'chat_conversation_id' => suggestedTopicsConversation($sibling, 'Sibling chat')->id,
        'suggested_label' => 'sibling-only',
    ]);

    $response = $this->actingAs($owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($response)->toBe([]);
});

it('only counts conversations from the suggestion window', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 2]);

    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;

    foreach (['Recent one', 'Recent two', 'Old one'] as $title) {
        ConversationTopic::create([
            'workspace_id' => $agent->workspace_id,
            'chat_conversation_id' => suggestedTopicsConversation($agent, $title)->id,
            'suggested_label' => 'refunds',
        ]);
    }

    // `title` is encrypted, so find the old row (the last one) by id.
    Conversation::query()->where('agent_id', $agent->id)->orderByDesc('id')->limit(1)->first()
        ?->forceFill(['created_at' => now()->subDays(91)])->saveQuietly();

    $response = $this->actingAs($owner)
        ->getJson(route('agents.topics.suggestions', $agent))
        ->assertOk()
        ->json('suggestions');

    expect($response)->toHaveCount(1)
        ->and($response[0]['count'])->toBe(2)
        ->and(array_column($response[0]['examples'], 'title'))->toEqualCanonicalizing(['Recent one', 'Recent two']);
});

it('reads the examples of every label with the same number of queries', function (): void {
    config(['chatvia.analytics.analysis.min_occurrences' => 1]);

    $agent = Agent::factory()->create();
    $owner = $agent->workspace->owner;
    $queries = 0;
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries++;
    });

    $countQueries = function () use ($owner, $agent, &$queries): int {
        $queries = 0;
        $this->actingAs($owner)->getJson(route('agents.topics.suggestions', $agent))->assertOk();

        return $queries;
    };

    ConversationTopic::create(['workspace_id' => $agent->workspace_id, 'chat_conversation_id' => suggestedTopicsConversation($agent, 'Refund')->id, 'suggested_label' => 'refunds']);
    $countQueries();
    $oneLabel = $countQueries();

    foreach (['shipping', 'returns'] as $label) {
        ConversationTopic::create(['workspace_id' => $agent->workspace_id, 'chat_conversation_id' => suggestedTopicsConversation($agent, ucfirst($label))->id, 'suggested_label' => $label]);
    }
    $threeLabels = $countQueries();

    expect($threeLabels)->toBe($oneLabel)
        ->and($this->actingAs($owner)->getJson(route('agents.topics.suggestions', $agent))->json('suggestions'))->toHaveCount(3);
});
