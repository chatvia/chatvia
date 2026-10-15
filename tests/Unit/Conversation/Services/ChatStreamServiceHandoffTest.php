<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * While a human operator holds a conversation, a visitor's message is relayed
 * to them instead of being answered by the AI — but only for the visitor's
 * own conversation.
 */
beforeEach(function (): void {
    Event::fake([MessagePosted::class]);
    Bus::fake();
});

/**
 * @return array{0: Agent, 1: Session, 2: Conversation}
 */
function heldConversation(): array
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => HandoffStatus::Human,
        'handoff_user_id' => $agent->workspace->owner->id,
    ]);

    return [$agent, $session, $conversation];
}

function heldConversationStream(Agent $agent, string $sessionId, string $conversationId): array
{
    return iterator_to_array(app(ChatStreamService::class)->stream(new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: $agent->llm, message: 'Are you there?'),
        externalSessionId: $sessionId,
        conversationId: $conversationId,
    )), preserve_keys: false);
}

it('relays the visitor message to the operator holding the conversation', function (): void {
    [$agent, $session, $conversation] = heldConversation();

    $events = heldConversationStream($agent, $session->external_id, $conversation->external_id);

    expect($events[1]->type)->toBe(EventType::Meta)
        ->and($events[1]->data['value']['status'])->toBe('human');

    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->conversationId === $conversation->external_id
        && $event->content === 'Are you there?');
});

it('does not relay into a conversation of another visitor session', function (): void {
    // The conversation is looked up inside the visitor's own session, so a
    // visitor who knows another conversation's id cannot post into it.
    [$agent, , $conversation] = heldConversation();
    $otherSession = Session::factory()->create(['agent_id' => $agent->id]);
    // The relay check runs before the per-turn gates, so refusing here stops
    // the unrelayed turn before it calls the model.
    test()->mock(AgentChatRateLimiter::class)->shouldReceive('visitorExceeded')->andReturnFalse()->getMock()->shouldReceive('exceeded')->andReturnTrue();

    heldConversationStream($agent, $otherSession->external_id, $conversation->external_id);

    Event::assertNotDispatched(MessagePosted::class);
});
