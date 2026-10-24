<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\SendVisitorHandoffMessageAction;
use Domain\Conversation\Data\Handoff\SendVisitorHandoffMessageData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Jobs\StoreMessageJob;
use Domain\Conversation\Models\Conversation;
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

function svhmConversation(HandoffStatus $status): Conversation
{
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);

    return Conversation::factory()->forAgent($agent)->handedOff($status)->create();
}

it('adds the message to the conversation and broadcasts it with the visitor session as sender', function (): void {
    $conversation = svhmConversation(HandoffStatus::Human);
    $session = $conversation->session;

    $messageId = app(SendVisitorHandoffMessageAction::class)->execute(
        new SendVisitorHandoffMessageData($conversation, 'Still there?'),
    );

    $history = app(ChatMemory::class)
        ->withScope(new ChatScopeData($session->agent, $session->external_id, $conversation->external_id))
        ->getMessagesForConversation();

    expect($messageId)->not->toBeNull()
        ->and($history[0]->id)->toBe($messageId)
        ->and($history[0]->content)->toBe('Still there?');

    Bus::assertDispatched(StoreMessageJob::class, fn (StoreMessageJob $job): bool => $job->payload->source === MessageOrigin::Handoff->value);
    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $event): bool => $event->role === 'user'
        && $event->senderId === $session->external_id
        && $event->senderName === 'Visitor');
});

it('counts a chatter’s relayed message against their monthly limit', function (): void {
    // A live chat is still the chatter talking: their limit counts the
    // relayed message like any other, through the turn's own counting.
    $agent = Agent::factory()->create(['llm_id' => Llm::factory()->create()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $conversation = Conversation::factory()->forAgent($agent)->ofChatter($chatter)->handedOff(HandoffStatus::Human)->create();
    app(SetMessageLimitAction::class)->execute($chatter, 5);

    app(SendVisitorHandoffMessageAction::class)->execute(
        new SendVisitorHandoffMessageData($conversation, 'Still there?'),
    );

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('messages_used'))->toBe(1);
});

it('does nothing when no human holds the conversation', function (): void {
    $conversation = svhmConversation(HandoffStatus::Ai);

    expect(app(SendVisitorHandoffMessageAction::class)->execute(new SendVisitorHandoffMessageData($conversation, 'Hello')))->toBeNull();

    Event::assertNotDispatched(MessagePosted::class);
});
