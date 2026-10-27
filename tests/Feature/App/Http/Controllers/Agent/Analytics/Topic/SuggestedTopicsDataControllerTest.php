<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The suggestions endpoint groups emergent (un-promoted) topic labels and
 * attaches example conversations: the grouped shape, the per-label cap, and
 * one query for the examples of every label.
 */
function stdConversation(Agent $agent, string $title): Conversation
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
        ConversationTopic::factory()->forConversation(stdConversation($agent, $title))->suggested('refunds')->create();
    }

    ConversationTopic::factory()->forConversation(stdConversation($agent, 'Shipping one'))->suggested('shipping')->create();

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
    // The response must carry plaintext, never the ciphertext blob (`eyJpdiI6...`).
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

    ConversationTopic::factory()->forConversation(stdConversation($sibling, 'Sibling chat'))->suggested('sibling-only')->create();

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
        ConversationTopic::factory()->forConversation(stdConversation($agent, $title))->suggested('refunds')->create();
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

    ConversationTopic::factory()->forConversation(stdConversation($agent, 'Refund'))->suggested('refunds')->create();
    $countQueries();
    $oneLabel = $countQueries();

    foreach (['shipping', 'returns'] as $label) {
        ConversationTopic::factory()->forConversation(stdConversation($agent, Str::ucfirst($label)))->suggested($label)->create();
    }
    $threeLabels = $countQueries();

    expect($threeLabels)->toBe($oneLabel)
        ->and($this->actingAs($owner)->getJson(route('agents.topics.suggestions', $agent))->json('suggestions'))->toHaveCount(3);
});
