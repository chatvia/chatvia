<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\PromoteSuggestedTopicAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Membership\Team\Exceptions\InviteRejectedException;
use Illuminate\Support\Facades\Event;

/**
 * PromoteSuggestedTopicAction turns an emergent suggestion label into a real,
 * agent-attached topic, converts every suggestion row carrying that label into
 * a committed topic row, and rolls each into analytics (one event per
 * conversation).
 */
function suggestionConversation(Agent $agent, string $label): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $conversation = Conversation::factory()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
    ]);

    ConversationTopic::create([
        'chat_conversation_id' => $conversation->id,
        'suggested_label' => $label,
    ]);

    return $conversation;
}

it('promotes a suggested label and backfills its conversations', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $conversations = [
        suggestionConversation($agent, 'Refund Requests'),
        suggestionConversation($agent, 'Refund Requests'),
    ];

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, $agent->user, 'Refund Requests');

    expect($topic->topic)->toBe('Refund Requests')
        ->and($agent->topics()->where('chat_topics.id', $topic->id)->exists())->toBeTrue();

    foreach ($conversations as $conversation) {
        expect(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('chat_topic_id', $topic->id)
            ->exists())->toBeTrue()
            ->and(ConversationTopic::query()
                ->where('chat_conversation_id', $conversation->id)
                ->whereNotNull('suggested_label')
                ->exists())->toBeFalse();
    }

    Event::assertDispatchedTimes(ConversationTopicAssigned::class, 2);
});

it('throws when the agent already has a topic with that name', function (): void {
    $agent = Agent::factory()->create();
    suggestionConversation($agent, 'Billing');

    // First promotion creates the "Billing" topic.
    app(PromoteSuggestedTopicAction::class)->execute($agent, $agent->user, 'Billing');

    expect(fn () => app(PromoteSuggestedTopicAction::class)->execute($agent, $agent->user, 'Billing'))
        ->toThrow(InviteRejectedException::class);
});
