<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Messages\BroadcastChatTypingAction;
use Domain\Conversation\Data\Messages\BroadcastChatTypingData;
use Domain\Conversation\Exceptions\ConversationNotHeldException;
use Domain\Conversation\Models\Conversation;

/**
 * Broadcasts an operator's typing indicator to the visitor of a conversation
 * the operator holds. Taking the conversation over first is the rule — the
 * visitor must never see "typing…" from someone who cannot reply.
 */
class SendHandoffTypingAction
{
    public function __construct(
        private readonly BroadcastChatTypingAction $broadcastTyping,
    ) {}

    /**
     * @throws ConversationNotHeldException when the operator has not taken the conversation over
     */
    public function execute(Conversation $conversation, User $operator, BroadcastChatTypingData $data): void
    {
        if (! $conversation->isHeldBy($operator)) {
            throw ConversationNotHeldException::forTyping();
        }

        $this->broadcastTyping->execute($data);
    }
}
