<?php

namespace Domain\Chat\Actions\Handoff;

use App\Auth\Models\User;
use Domain\Chat\Events\ChatHandedOff;
use Domain\Chat\Models\ChatConversation;

class ReturnConversationToAiAction
{
    public function execute(ChatConversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeReturnedBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => 'returned',
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        ChatHandedOff::dispatch(
            $conversation->external_id,
            'returned',
            (string) $admin->id,
            $admin->name,
        );

        $conversation->update(['handoff_status' => 'ai']);

        return true;
    }
}
