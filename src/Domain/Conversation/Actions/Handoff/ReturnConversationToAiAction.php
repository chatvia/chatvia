<?php

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

class ReturnConversationToAiAction
{
    public function execute(Conversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeReturnedBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'returned',
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            'returned',
            (string) $admin->id,
            $admin->name,
        );

        $conversation->update(['handoff_status' => 'ai']);

        return true;
    }
}
