<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Support\Localization\Language\Models\Language;

/**
 * AnalyzeConversationAction is the end-of-life pass: one LLM call over the full
 * transcript records every applicable existing topic + emergent suggestions and
 * sets language/tone/sentiment, exactly once (analyzed_at guard). Topic
 * assignment also dispatches ConversationTopicAssigned, which rolls up
 * analytics_agent_topic_daily.
 */
function acaConversation(Agent $agent, int $userMessages = 3): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    for ($i = 0; $i < $userMessages; $i++) {
        Message::factory()->fromUser()->create([
            'chat_conversation_id' => $conversation->id,
            'content' => "User question {$i}",
        ]);
    }

    return $conversation;
}

/**
 * Topic assignment is opt-in (the `analytics` settings group defaults `topics`
 * to false — see config/model-settings.php), so agents under test enable it
 * explicitly. Sentiment/tone are stored by this pass regardless of settings.
 */
function acaEnableTopics(Agent $agent): void
{
    $agent->setSetting('topics', true, 'analytics');
}

it('assigns multiple topics, records a suggestion, sets fields and stamps analyzed_at', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    $ids = attachAgentTopics($agent, ['Billing', 'Bug Report']);
    $conversation = acaConversation($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing'], $ids['Bug Report'], 999999],
        'suggested_topics' => ['Refund Requests'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);
    $conversation->refresh();

    $english = Language::query()->where('code', 'en')->value('id');

    expect($conversation->analyzed_at)->not->toBeNull()
        ->and($conversation->sentiment)->toBe('negative')
        ->and($conversation->tone)->toBe('frustrated')
        ->and($english)->not->toBeNull()
        ->and($conversation->language_id)->toBe($english);

    $committed = ConversationTopic::query()
        ->where('chat_conversation_id', $conversation->id)
        ->whereNotNull('topic_id')
        ->pluck('topic_id')
        ->sort()
        ->values()
        ->all();

    expect($committed)->toBe(collect([$ids['Billing'], $ids['Bug Report']])->sort()->values()->all());

    expect(ConversationTopic::query()
        ->where('chat_conversation_id', $conversation->id)
        ->where('suggested_label', 'Refund Requests')
        ->exists())->toBeTrue();

    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 2);
});

it('is idempotent — a second run changes nothing', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    $ids = attachAgentTopics($agent, ['Billing']);
    $conversation = acaConversation($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing']],
        'suggested_topics' => [],
        'sentiment' => 'neutral',
        'tone' => 'neutral',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);
    app(AnalyzeConversationAction::class)->execute($conversation->refresh());

    expect(ConversationTopic::where('chat_conversation_id', $conversation->id)->count())->toBe(1);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 1);
});

it('stores sentiment and tone but no topics when the agent has topics switched off', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $agent->setSetting('topics', false, 'analytics');
    $ids = attachAgentTopics($agent, ['Billing']);
    $conversation = acaConversation($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing']],
        'suggested_topics' => ['Refund Requests'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);
    $conversation->refresh();

    expect($conversation->analyzed_at)->not->toBeNull()
        ->and($conversation->sentiment)->toBe('negative')
        ->and($conversation->tone)->toBe('frustrated')
        ->and(ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
    Event::assertNotDispatched(ConversationTopicAssigned::class);
});

it('stamps analyzed_at without a model call or a counted attempt when there are no user messages', function (): void {
    Http::fake();

    $agent = Agent::factory()->create();
    $conversation = acaConversation($agent, userMessages: 0);
    Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => $conversation->id,
        'content' => 'Hello, how can I help?',
    ]);

    app(AnalyzeConversationAction::class)->execute($conversation);

    $conversation->refresh();
    expect($conversation->analyzed_at)->not->toBeNull()
        ->and($conversation->analysis_attempts)->toBe(0)
        ->and(ConversationTopic::where('chat_conversation_id', $conversation->id)->count())->toBe(0);
    Http::assertNothingSent();
});

it('rolls the assigned topic into analytics_agent_topic_daily', function (): void {
    config(['queue.default' => 'sync']); // run the queued RecordAnalytics subscriber inline

    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    $ids = attachAgentTopics($agent, ['Billing']);
    $conversation = acaConversation($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing']],
        'suggested_topics' => [],
        'sentiment' => 'neutral',
        'tone' => 'neutral',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);

    $row = DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_topic_daily')
        ->where('agent_id', $agent->id)
        ->where('topic_id', $ids['Billing'])
        ->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->conversation_count)->toBe(1)
        ->and((string) $row->bucket_day)->toBe($conversation->created_at->format('Y-m-d'));
});

it('leaves the conversation pending when the model output cannot be parsed', function (): void {
    // Stamping analyzed_at with a guessed "neutral" would count the conversation
    // in the rollups and the scheduled scan would never retry it.
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    attachAgentTopics($agent, ['Billing']);
    $conversation = acaConversation($agent);

    fakeLlmCompletion('Sorry, I could not analyze that.');

    app(AnalyzeConversationAction::class)->execute($conversation);

    $conversation->refresh();
    expect($conversation->analyzed_at)->toBeNull()
        ->and($conversation->analysis_attempts)->toBe(1)
        ->and($conversation->sentiment)->toBeNull()
        ->and($conversation->tone)->toBeNull()
        ->and(ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
    Event::assertNotDispatched(ConversationTopicAssigned::class);
});

it('counts the attempt of a run whose model call cannot connect, and lets the error through', function (): void {
    // ConversationAnalyzer forgives only an HTTP error answer. A connection
    // failure fails the job; the conversation stays pending, so the scheduled
    // scan tries again until its attempts are used up.
    $agent = Agent::factory()->create();
    $conversation = acaConversation($agent);

    Http::fake(['*' => Http::failedConnection()]);

    expect(function () use ($conversation): void {
        app(AnalyzeConversationAction::class)->execute($conversation);
    })->toThrow(ConnectionException::class);

    expect($conversation->refresh()->analysis_attempts)->toBe(1)
        ->and($conversation->analyzed_at)->toBeNull();
});
