<?php

namespace Domain\Chat\Actions;

use App\Auth\Models\User;
use Domain\Chat\Events\ChatHandoffEvent;
use Domain\Chat\Models\ChatConversation;

class TakeoverConversationAction
{
    public function execute(ChatConversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeTakenOverBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'human',
            'handoff_user_id' => $admin->id,
            'handoff_at' => now(),
        ]);

        ChatHandoffEvent::dispatch(
            $conversation->external_id,
            'human',
            (string) $admin->id,
            $admin->name,
        );

        return true;
    }
}
