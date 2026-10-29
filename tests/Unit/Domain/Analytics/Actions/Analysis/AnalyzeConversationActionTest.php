<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Analysis\AnalyzeConversationAction;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Support\Facades\Event;
use Support\Localization\Language\Models\Language;

/**
 * AnalyzeConversationAction is the end-of-life pass: one LLM call over the full
 * transcript records every applicable existing topic + emergent suggestions and
 * sets language/tone/sentiment, exactly once (analyzed_at guard). Output it
 * cannot parse leaves the conversation pending for a retry.
 */
function acaConversation(Agent $agent): Conversation
{
    $conversation = Conversation::factory()->create(['agent_id' => $agent->id]);

    for ($i = 0; $i < 3; $i++) {
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

it('assigns the topics, records a suggestion and sets the fields exactly once', function (): void {
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
    app(AnalyzeConversationAction::class)->execute($conversation->refresh());

    $committed = ConversationTopic::query()
        ->where('chat_conversation_id', $conversation->id)
        ->whereNotNull('topic_id')
        ->pluck('topic_id')
        ->sort()
        ->values()
        ->all();

    expect($conversation->analyzed_at)->not->toBeNull()
        ->and($conversation->sentiment)->toBe(Sentiment::Negative)
        ->and($conversation->tone)->toBe(Tone::Frustrated)
        ->and($conversation->language_id)->toBe(Language::query()->where('code', 'en')->value('id'))
        ->and($committed)->toBe(collect([$ids['Billing'], $ids['Bug Report']])->sort()->values()->all())
        ->and(ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->where('suggested_label', 'Refund Requests')->count())->toBe(1);
    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 2);
});

it('leaves the conversation pending when the model output cannot be parsed', function (): void {
    Event::fake([ConversationTopicAssigned::class]);
    $agent = Agent::factory()->create();
    acaEnableTopics($agent);
    attachAgentTopics($agent, ['Billing']);
    $conversation = acaConversation($agent);
    fakeLlmCompletion('Sorry, I could not analyze that.');

    app(AnalyzeConversationAction::class)->execute($conversation);

    expect($conversation->refresh()->analyzed_at)->toBeNull()
        ->and($conversation->analysis_attempts)->toBe(1)
        ->and($conversation->sentiment)->toBeNull()
        ->and($conversation->tone)->toBeNull()
        ->and(ConversationTopic::query()->where('chat_conversation_id', $conversation->id)->exists())->toBeFalse();
    Event::assertNotDispatched(ConversationTopicAssigned::class);
});
