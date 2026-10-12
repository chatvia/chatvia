<?php

use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Topics\PromoteSuggestedTopicAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Illuminate\Support\Facades\Event;
use Support\Exceptions\DomainValidationException;

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

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Refund Requests');

    expect($topic->topic)->toBe('Refund Requests')
        ->and($agent->topics()->where('topics.id', $topic->id)->exists())->toBeTrue();

    foreach ($conversations as $conversation) {
        expect(ConversationTopic::query()
            ->where('chat_conversation_id', $conversation->id)
            ->where('topic_id', $topic->id)
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
    app(PromoteSuggestedTopicAction::class)->execute($agent, 'Billing');

    expect(fn () => app(PromoteSuggestedTopicAction::class)->execute($agent, 'Billing'))
        ->toThrow(DomainValidationException::class);
});

it('promotes the label on old conversations too, not only on recent ones', function (): void {
    Event::fake([ConversationTopicAssigned::class]);

    $agent = Agent::factory()->create();
    $old = suggestionConversation($agent, 'Shipping Delays');
    $old->forceFill(['created_at' => now()->subDays(200)])->saveQuietly();

    $topic = app(PromoteSuggestedTopicAction::class)->execute($agent, 'Shipping Delays');

    expect(ConversationTopic::query()
        ->where('chat_conversation_id', $old->id)
        ->where('topic_id', $topic->id)
        ->exists())->toBeTrue();
});
