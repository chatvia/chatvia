<?php

namespace Domain\Conversation\Actions\Handoff;

use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

class EndHandoffChatAction
{
    /**
     * Returns true if the handoff was ended; false if it wasn't currently in
     * human mode (no-op).
     */
    public function execute(Conversation $conversation): bool
    {
        if ($conversation->handoff_status !== 'human') {
            return false;
        }

        $adminId = $conversation->handoff_user_id;
        $adminName = $conversation->handoffUser?->name;

        $conversation->update([
            'handoff_status' => 'ai',
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            'user_ended',
            (string) $adminId,
            $adminName,
        );

        return true;
    }
}
