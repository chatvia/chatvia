<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

/**
 * A visitor's message during a live chat goes to the operator: it is added to
 * the conversation (marked as a handoff message) and broadcast — the AI does
 * not answer.
 */
beforeEach(function (): void {
    Event::fake([MessagePosted::class]);
    Bus::fake();
});

function visitorHandoffConversation(HandoffStatus $status): Conversation
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => $status,
    ]);
}

it('adds the message to the conversation and broadcasts it with the visitor session as sender', function (): void {
    $conversation = visitorHandoffConversation(HandoffStatus::Human);
    $session = $conversation->session;

    $messageId = app(SendVisitorHandoffMessageAction::class)->execute(
        new SendVisitorHandoffMessageData($conversation, 'Still there?'),
    );

    $history = app(ChatMemory::class)
        ->withScope(new ChatScopeData($session->agent, $session->external_id, $conversation->external_id))
        ->getMessagesForConversation();

    expect($messageId)->not->toBeNull()
        ->and($history[0]['id'])->toBe($messageId)
        ->and($history[0]['content'])->toBe('Still there?');

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload->source === SendVisitorHandoffMessageAction::SOURCE);
    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->role === 'user'
        && $event->senderId === $session->external_id
        && $event->senderName === 'Visitor');
});

it('does nothing when no human holds the conversation', function (): void {
    $conversation = visitorHandoffConversation(HandoffStatus::Ai);

    expect(app(SendVisitorHandoffMessageAction::class)->execute(new SendVisitorHandoffMessageData($conversation, 'Hello')))->toBeNull();

    Event::assertNotDispatched(MessagePosted::class);
});
