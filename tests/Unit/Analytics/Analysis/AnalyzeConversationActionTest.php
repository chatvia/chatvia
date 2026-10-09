<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * AnalyzeConversationAction is the end-of-life pass: one LLM call over the full
 * transcript records every applicable existing topic + emergent suggestions and
 * sets language/tone/sentiment, exactly once (analyzed_at guard). Topic
 * assignment also dispatches ConversationTopicAssigned, which rolls up
 * analytics_agent_topic_daily.
 */
function conversationFor(Agent $agent, int $userMessages = 3): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        // Pinned: the factory's random title can overflow the stale test DB's
        // varchar(255) once encrypted (the in-repo migration declares text).
        'title' => null,
    ]);

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

/** @return array<string, int> */
function attachTopics(Agent $agent, array $names): array
{
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

    return $ids;
}

it('assigns multiple topics, records a suggestion, sets fields and stamps analyzed_at', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = proPlanAgent();
    acaEnableTopics($agent);
    $ids = attachTopics($agent, ['Billing', 'Bug Report']);
    $conversation = conversationFor($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing'], $ids['Bug Report'], 999999],
        'suggested_topics' => ['Refund Requests'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);
    $conversation->refresh();

    expect($conversation->analyzed_at)->not->toBeNull()
        ->and($conversation->sentiment)->toBe('negative')
        ->and($conversation->tone)->toBe('frustrated');

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

    $agent = proPlanAgent();
    acaEnableTopics($agent);
    $ids = attachTopics($agent, ['Billing']);
    $conversation = conversationFor($agent);

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

it('stores the full analysis regardless of plan capabilities', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    // Starter plan — no analytics capabilities. The single call still runs and
    // everything it returns is stored; capability only gates the analytics
    // screens (route middleware), not the analysis itself.
    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    $ids = attachTopics($agent, ['Billing']);
    $conversation = conversationFor($agent);

    fakeLlmCompletion(json_encode([
        'topic_ids' => [$ids['Billing']],
        'suggested_topics' => ['Some Theme'],
        'sentiment' => 'negative',
        'tone' => 'frustrated',
        'language' => 'en',
    ]));

    app(AnalyzeConversationAction::class)->execute($conversation);
    $conversation->refresh();

    expect($conversation->sentiment)->toBe('negative')
        ->and($conversation->tone)->toBe('frustrated')
        ->and(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('topic_id', $ids['Billing'])
            ->exists())->toBeTrue()
        ->and(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('suggested_label', 'Some Theme')
            ->exists())->toBeTrue();

    Event::assertDispatched(ConversationTopicAssigned::class);
});

it('stamps analyzed_at and skips analysis when there are no user messages', function (): void {
    $agent = proPlanAgent();
    $conversation = conversationFor($agent, userMessages: 0);
    Message::factory()->create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Hello, how can I help?',
    ]);

    app(AnalyzeConversationAction::class)->execute($conversation);

    expect($conversation->refresh()->analyzed_at)->not->toBeNull()
        ->and(ConversationTopic::where('chat_conversation_id', $conversation->id)->count())->toBe(0);
});

it('rolls the assigned topic into analytics_agent_topic_daily', function (): void {
    config(['queue.default' => 'sync']); // run the queued RecordAnalytics subscriber inline

    $agent = proPlanAgent();
    acaEnableTopics($agent);
    $ids = attachTopics($agent, ['Billing']);
    $conversation = conversationFor($agent);

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

    $agent = proPlanAgent();
    acaEnableTopics($agent);
    attachTopics($agent, ['Billing']);
    $conversation = conversationFor($agent);

    fakeLlmCompletion('Sorry, I could not analyze that.');

    app(AnalyzeConversationAction::class)->execute($conversation);

    $conversation->refresh();
    expect($conversation->analyzed_at)->toBeNull()
        ->and($conversation->sentiment)->toBeNull()
        ->and($conversation->tone)->toBeNull()
        ->and(ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
    Event::assertNotDispatched(ConversationTopicAssigned::class);
});
