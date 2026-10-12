<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\ToneEnum;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;

/**
 * filterByTopic now matches through the conversation_topics pivot, so a
 * multi-label conversation appears under each of its topics.
 */
it('matches conversations carrying the requested topic, multi-label aware', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $billing = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Billing', 'description' => 'x']);
    $bug = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Bug', 'description' => 'y']);

    $multi = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $multi->topics()->attach([$billing->id, $bug->id]);

    $single = Conversation::factory()->create(['chat_session_id' => $session->id, 'source' => ConversationSource::Widget]);
    $single->topics()->attach([$bug->id]);

    $billingMatches = Conversation::query()->filterByTopic((string) $billing->id)->pluck('id')->all();
    expect($billingMatches)->toContain($multi->id)
        ->and($billingMatches)->not->toContain($single->id);

    $bugMatches = Conversation::query()->filterByTopic((string) $bug->id)->pluck('id')->all();
    expect($bugMatches)->toContain($multi->id)
        ->and($bugMatches)->toContain($single->id);
});

/**
 * Sentiment/tone are stamped on the conversation row by the consolidated
 * analysis pass — the chat-log UI renders chat_conversations.sentiment, so the
 * filter must match that column, not per-message sentiment.
 */
it('filters by the conversation-level sentiment column', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $positive = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'sentiment' => 'positive',
    ]);
    $negative = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'sentiment' => 'negative',
    ]);

    $matches = Conversation::query()->filterBySentiment('positive')->pluck('id')->all();

    expect($matches)->toContain($positive->id)
        ->and($matches)->not->toContain($negative->id);
});

it('filters by the conversation-level tone column', function (): void {
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $tone = ToneEnum::cases()[0]->value;
    $otherTone = ToneEnum::cases()[1]->value;

    $matching = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'tone' => $tone,
    ]);
    $nonMatching = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'tone' => $otherTone,
    ]);

    $matches = Conversation::query()->filterByTone($tone)->pluck('id')->all();

    expect($matches)->toContain($matching->id)
        ->and($matches)->not->toContain($nonMatching->id);
});
