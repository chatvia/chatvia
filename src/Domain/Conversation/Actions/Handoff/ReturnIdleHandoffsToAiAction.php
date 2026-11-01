<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Events\HandedOff;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Hands live chats back to the AI once neither the visitor nor the teammate
 * has written for the agent's idle time ({@see Agent::handoffIdleMinutes()}).
 * Without it a chat stays with a person until someone hands it back, and
 * the AI never answers that visitor again. Idle is measured from the last
 * message (or the takeover), not `last_activity_at`: an open widget keeps
 * that fresh with its heartbeat. Returns how many chats went back.
 */
class ReturnIdleHandoffsToAiAction
{
    public function execute(): int
    {
        $returned = 0;

        Conversation::query()
            ->whereNotNull('handoff_user_id')
            ->where('handoff_status', HandoffStatus::Human)
            ->with(['agent', 'handoffUser'])
            ->withMax('messages', 'created_at')
            ->lazyById()
            ->each(function (Conversation $conversation) use (&$returned): void {
                if ($this->returnIfIdle($conversation)) {
                    $returned++;
                }
            });

        return $returned;
    }

    private function returnIfIdle(Conversation $conversation): bool
    {
        $agent = $conversation->agent;

        if (! $agent) {
            return false;
        }

        $cutoff = now()->subMinutes($agent->handoffIdleMinutes());
        $lastMessageAt = $conversation->getAttribute('messages_max_created_at');

        if ($conversation->handoff_at?->gte($cutoff) || ($lastMessageAt !== null && Carbon::parse($lastMessageAt)->gte($cutoff))) {
            return false;
        }

        $holderId = $conversation->handoff_user_id;

        // Re-checked in the write: a message or a new takeover since the read
        // keeps the chat with the person.
        $changed = Conversation::query()
            ->whereKey($conversation->getKey())
            ->where('handoff_status', HandoffStatus::Human)
            ->where('handoff_user_id', $holderId)
            ->where(fn (Builder $query) => $query->whereNull('handoff_at')->orWhere('handoff_at', '<', $cutoff))
            ->whereDoesntHave('messages', fn (Builder $query) => $query->where('created_at', '>=', $cutoff))
            ->update([
                'handoff_status' => HandoffStatus::Returned,
                'handoff_user_id' => null,
                'handoff_at' => null,
                'updated_at' => now(),
            ]);

        if ($changed === 0) {
            return false;
        }

        // Same order as a teammate handing back: `returned` for the widget to
        // show the switch, then the stored status settles on `ai`.
        HandedOff::dispatch(
            $conversation->external_id,
            HandoffStatus::Returned->value,
            (string) $holderId,
            $conversation->handoffUser?->name,
        );

        Conversation::query()
            ->whereKey($conversation->getKey())
            ->where('handoff_status', HandoffStatus::Returned)
            ->update(['handoff_status' => HandoffStatus::Ai]);

        return true;
    }
}
