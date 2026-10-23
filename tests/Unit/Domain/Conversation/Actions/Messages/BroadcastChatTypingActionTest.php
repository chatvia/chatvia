<?php

declare(strict_types=1);

use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Events\TypingStarted;
use Illuminate\Support\Facades\Event;

/**
 * The typing indicator is a pure broadcast: the action hands every field of the
 * DTO to one TypingStarted event, which the frontend listens for as `typing`.
 */
it('dispatches one TypingStarted event carrying every field', function (): void {
    Event::fake([TypingStarted::class]);

    app(BroadcastChatTypingAction::class)->execute(new BroadcastChatTypingData(
        conversationId: 'conv-123',
        isTyping: true,
        typerType: 'agent',
        typerName: 'Sara',
        typerId: 'op-7',
    ));

    Event::assertDispatchedTimes(TypingStarted::class, 1);
    Event::assertDispatched(TypingStarted::class, fn (TypingStarted $event): bool => $event->conversationId === 'conv-123'
        && $event->isTyping
        && $event->typerType === 'agent'
        && $event->typerName === 'Sara'
        && $event->typerId === 'op-7'
        && $event->broadcastAs() === 'typing');
});
