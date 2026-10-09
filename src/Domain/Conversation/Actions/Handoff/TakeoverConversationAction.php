<?php

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

/**
 * A human operator takes a conversation over from the AI. Returns false when
 * another operator already holds it or the operator cannot access the agent.
 */
class TakeoverConversationAction
{
    public function execute(Conversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeTakenOverBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => HandoffStatus::Human,
            'handoff_user_id' => $admin->id,
            'handoff_at' => now(),
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            HandoffStatus::Human->value,
            (string) $admin->id,
            $admin->name,
        );

        return true;
    }
}
