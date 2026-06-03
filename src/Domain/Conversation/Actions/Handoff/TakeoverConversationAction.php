<?php

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

class TakeoverConversationAction
{
    public function execute(Conversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeTakenOverBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'human',
            'handoff_user_id' => $admin->id,
            'handoff_at' => now(),
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            'human',
            (string) $admin->id,
            $admin->name,
        );

        return true;
    }
}
