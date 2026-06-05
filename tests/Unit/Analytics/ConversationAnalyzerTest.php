<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Analytics\Services\ConversationAnalyzer;
use Domain\Conversation\Topic\Models\Topic;

/**
 * ConversationAnalyzer makes one LLM call and parses a JSON object into a
 * ConversationAnalysisData. The parsing must be defensive: constrain topic ids
 * to the agent's allowed set, drop suggestions that duplicate existing topics,
 * and never throw on malformed output.
 *
 * @return array{0: Agent, 1: array<string, int>}
 */
function agentWithTopics(array $names): array
{
    $agent = Agent::factory()->create();
    $ids = [];

    foreach ($names as $name) {
        $topic = Topic::create([
            'user_id' => $agent->user_id,
            'topic' => $name,
            'description' => "About {$name}",
        ]);
        $agent->topics()->attach($topic->id);
        $ids[$name] = $topic->id;
    }

    return [$agent, $ids];
}

it('keeps only topic ids the agent actually has', function (): void {
    [$agent, $ids] = agentWithTopics(['Billing', 'Bug Report']);

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
    [$agent] = agentWithTopics(['Billing']);

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

it('returns safe defaults when the model output is not valid JSON', function (): void {
    [$agent] = agentWithTopics(['Billing']);

    fakeLlmCompletion('Sorry, I could not analyze that.');

    $result = app(ConversationAnalyzer::class)->analyze('User: hello', $agent);

    expect($result->topicIds)->toBe([])
        ->and($result->suggestedTopics)->toBe([])
        ->and($result->sentiment)->toBe(SentimentEnum::NEUTRAL)
        ->and($result->tone)->toBe(ToneEnum::NEUTRAL)
        ->and($result->language)->toBeNull();
});
