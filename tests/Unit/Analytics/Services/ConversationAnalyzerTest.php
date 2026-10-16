<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * ConversationAnalyzer makes one LLM call and parses a JSON object into a
 * ConversationAnalysisData. The parsing must be defensive: constrain topic ids
 * to the agent's allowed set, drop suggestions that duplicate existing topics,
 * and never throw on malformed output — it returns null so nothing is saved.
 *
 * @param  list<string>  $names
 * @return array{0: Agent, 1: array<string, int>}
 */
function cazAgentWithTopics(array $names): array
{
    $agent = Agent::factory()->create();

    return [$agent, attachAgentTopics($agent, $names)];
}

it('keeps only topic ids the agent actually has', function (): void {
    [$agent, $ids] = cazAgentWithTopics(['Billing', 'Bug Report']);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing'], 999999], // 999999 is not attached to this agent
        'suggested_topics' => ['Refund Requests'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'EN',
    ]));

    $result = app(ConversationAnalyzer::class)->analyze('User: my invoice is wrong', $agent);

    expect($result->topicIds)->toBe([$ids['Billing']])
        ->and($result->suggestedTopics)->toBe(['Refund Requests'])
        ->and($result->sentiment)->toBe(SentimentEnum::NEGATIVE)
        ->and($result->tone)->toBe(ToneEnum::FRUSTRATED)
        ->and($result->language)->toBe('en');
});

it('drops a suggested label that duplicates an existing topic name', function (): void {
    [$agent] = cazAgentWithTopics(['Billing']);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [],
        'suggested_topics' => ['billing', 'New Theme'], // "billing" already exists as "Billing"
        'sentiment' => 'neutral',
        'tone' => 'neutral',
        'language' => 'en',
    ]));

    $result = app(ConversationAnalyzer::class)->analyze('User: hello', $agent);

    expect($result->suggestedTopics)->toBe(['New Theme']);
});

it('returns null instead of a guessed result when the model output is not valid JSON', function (string $content): void {
    [$agent] = cazAgentWithTopics(['Billing']);

    fakeLlmCompletion($content);

    expect(app(ConversationAnalyzer::class)->analyze('User: hello', $agent))->toBeNull()
        ->and(app(ConversationAnalyzer::class)->analyzeMessage('hello'))->toBeNull();
})->with([
    'prose' => 'Sorry, I could not analyze that.',
    'empty' => '',
    'cut off' => '{"sentiment": "neg',
]);

it('shows the model the pending labels of recent conversations only', function (): void {
    [$agent] = cazAgentWithTopics([]);

    $conversation = function (Agent $agent, string $label, int $daysAgo): void {
        $conversation = Conversation::factory()->create(['agent_id' => $agent->id, 'created_at' => now()->subDays($daysAgo)]);

        ConversationTopic::create(['chat_conversation_id' => $conversation->id, 'suggested_label' => $label]);
    };

    $conversation($agent, 'Recent Theme', 3);
    $conversation($agent, 'Old Theme', 120);

    fakeLlmCompletion(json_encode(['topic_ids' => [], 'suggested_topics' => [], 'sentiment' => 'neutral', 'tone' => 'neutral', 'language' => 'en']));

    app(ConversationAnalyzer::class)->analyze('User: hello', $agent);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'Recent Theme')
        && ! str_contains($request->body(), 'Old Theme'));
});
