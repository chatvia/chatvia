<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

/**
 * The visitor ends a live chat with a human operator; the AI answers again.
 */
class EndHandoffChatAction
{
    /** Broadcast reason the widget and dashboard use for a visitor-ended chat. */
    public const string ENDED_BY_VISITOR = 'user_ended';

    /**
     * Returns true when the handoff was ended, false when the conversation was
     * not with a human (nothing to do).
     */
    public function execute(Conversation $conversation): bool
    {
        if (! $conversation->isHandedOff()) {
            return false;
        }

        $adminId = $conversation->handoff_user_id;
        $adminName = $conversation->handoffUser?->name;

        $conversation->update([
            'handoff_status' => HandoffStatus::Ai,
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            self::ENDED_BY_VISITOR,
            (string) $adminId,
            $adminName,
        );

        return true;
    }
}
