<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Handoff\SendHandoffTypingAction;
use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\TypingStarted;
use Domain\Conversation\Exceptions\ConversationNotHeldException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * Typing indicators reach the visitor only from the operator who took the
 * conversation over — anyone else is refused before anything is broadcast.
 */
function shtaConversation(User $holder): Conversation
{
    return Conversation::factory()->forAgent(createAgent($holder))->handedOff(HandoffStatus::Human, $holder)->create();
}

function shtaTyping(Conversation $conversation, User $operator): BroadcastChatTypingData
{
    return new BroadcastChatTypingData(
        conversationId: $conversation->external_id,
        isTyping: true,
        typerType: MessageRole::Operator->value,
        typerName: $operator->name,
        typerId: (string) $operator->id,
    );
}

it('broadcasts typing for the operator who holds the conversation', function (): void {
    Event::fake([TypingStarted::class]);
    $holder = User::factory()->create();
    $conversation = shtaConversation($holder);

    app(SendHandoffTypingAction::class)->execute($conversation, $holder, shtaTyping($conversation, $holder));

    Event::assertDispatched(TypingStarted::class);
});

it('refuses an operator who has not taken the conversation over', function (): void {
    Event::fake([TypingStarted::class]);
    $holder = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = shtaConversation($holder);

    expect(fn (): mixed => app(SendHandoffTypingAction::class)->execute($conversation, $stranger, shtaTyping($conversation, $stranger)))
        ->toThrow(ConversationNotHeldException::class);

    Event::assertNotDispatched(TypingStarted::class);
});
