<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Analytics\Services\ConversationAnalyzer;

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

it('keeps only the agent\'s topic ids and drops a suggestion that duplicates a topic', function (): void {
    [$agent, $ids] = cazAgentWithTopics(['Billing', 'Bug Report']);
    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing'], 999999],
        'suggested_topics' => ['billing', 'Refund Requests'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'EN',
    ]));

    $result = app(ConversationAnalyzer::class)->analyze('User: my invoice is wrong', $agent);

    expect($result->topicIds)->toBe([$ids['Billing']])
        ->and($result->suggestedTopics)->toBe(['Refund Requests'])
        ->and($result->sentiment)->toBe(Sentiment::Negative)
        ->and($result->tone)->toBe(Tone::Frustrated)
        ->and($result->language)->toBe('en');
});

it('returns null instead of a guessed result when the model output is not valid JSON', function (): void {
    [$agent] = cazAgentWithTopics(['Billing']);
    fakeLlmCompletion('{"sentiment": "neg');

    expect(app(ConversationAnalyzer::class)->analyze('User: hello', $agent))->toBeNull()
        ->and(app(ConversationAnalyzer::class)->analyzeMessage('hello'))->toBeNull();
});
