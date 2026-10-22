<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Handoff;

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;

/**
 * A human operator takes a conversation over from the AI. Returns false when
 * another operator already holds it or the operator cannot access the agent.
 *
 * The write itself checks that nobody else holds the conversation, so when
 * two operators take it over at the same moment only one of them wins.
 */
class TakeoverConversationAction
{
    public function execute(Conversation $conversation, User $admin): bool
    {
        if (! $conversation->canBeTakenOverBy($admin)) {
            return false;
        }

        $now = now();
        $changes = [
            'handoff_status' => HandoffStatus::Human,
            'handoff_user_id' => $admin->id,
            'handoff_at' => $now,
            'updated_at' => $now,
        ];

        $taken = Conversation::query()
            ->whereKey($conversation->getKey())
            ->where(fn (Builder $query) => $query
                ->where('handoff_status', '<>', HandoffStatus::Human)
                ->orWhere('handoff_user_id', $admin->id))
            ->update($changes);

        if ($taken === 0) {
            return false;
        }

        // The row changed under the model: match it, so the caller's reply
        // shows the new holder.
        $conversation->forceFill($changes)->syncOriginal();

        HandedOff::dispatch(
            $conversation->external_id,
            HandoffStatus::Human->value,
            (string) $admin->id,
            $admin->name,
        );

        return true;
    }
}
