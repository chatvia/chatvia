<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;

/**
 * The operator who holds a conversation hands it back to the AI. The widget
 * is told `returned` first (so it can show the switch), then the stored status
 * settles on `ai`. Returns false when this operator does not hold it.
 */
class ReturnConversationToAiAction
{
    public function execute(Conversation $conversation, User $admin): bool
    {
        if (! $conversation->isHeldBy($admin)) {
            return false;
        }

        $conversation->update([
            'handoff_status' => HandoffStatus::Returned,
            'handoff_user_id' => null,
            'handoff_at' => null,
        ]);

        HandedOff::dispatch(
            $conversation->external_id,
            HandoffStatus::Returned->value,
            (string) $admin->id,
            $admin->name,
        );

        $conversation->update(['handoff_status' => HandoffStatus::Ai]);

        return true;
    }
}
