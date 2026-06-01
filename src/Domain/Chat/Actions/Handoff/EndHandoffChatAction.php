<?php

namespace Domain\Chat\Actions\Handoff;

use Domain\Chat\Events\ChatHandedOff;
use Domain\Chat\Models\ChatConversation;

class EndHandoffChatAction
{
    /**
     * Returns true if the handoff was ended; false if it wasn't currently in
     * human mode (no-op).
     */
    public function execute(ChatConversation $conversation): bool
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

        ChatHandedOff::dispatch(
            $conversation->external_id,
            'user_ended',
            (string) $adminId,
            $adminName,
        );

        return true;
    }
}
