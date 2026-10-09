<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\ChatterAllowance\RecordChatterMessageAction;
use Domain\Billing\Actions\ChatterAllowance\SetChatterMessageLimitAction;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\Cache;

/**
 * Read side of the per-chatter monthly MESSAGE limit. The limit lives on the
 * chatter's OWN `user_credits` rows (one per calendar month: `messages` is the
 * cap, `[started_at, ends_at]` the window) with `user_usages.messages` as the
 * counter. Writes are Actions: {@see SetChatterMessageLimitAction} (the admin
 * sets or clears the cap) and {@see RecordChatterMessageAction} (a message is
 * counted).
 *
 * Separate from {@see UserCreditSystem}, the workspace owner's pool: a chatter
 * cannot use that (it resolves the owner's pool), so this reads the chatter's
 * own rows. Every method is neutral for non-chatters. No limit means no credit
 * row.
 *
 * The send check ({@see canSendMessages()}) is cached as a yes/no flag per
 * chatter. The Actions invalidate it ({@see forget()}) at the three state
 * changes — the cap is set or cleared, a new month's window opens, or a message
 * uses up the cap — so ordinary sends never hit the database here.
 */
class ChatterMessageAllowance
{
    private const string CAN_SEND_CACHE_KEY = 'chatter:{id}:can-send-messages';

    /** Backstop TTL only; the flag is invalidated explicitly on every state change. */
    private const int CACHE_TTL_HOURS = 6;

    /**
     * Cached yes/no: may this chatter still send this month? Always true for
     * non-chatters and for chatters without a limit.
     */
    public function canSendMessages(User $chatter): bool
    {
        if (! $chatter->isChatter()) {
            return true;
        }

        $cached = Cache::get($this->cacheKey($chatter->id));

        if ($cached !== null) {
            return (bool) $cached;
        }

        $canSend = ! $this->capExhausted($chatter);

        Cache::put($this->cacheKey($chatter->id), $canSend, now()->addHours(self::CACHE_TTL_HOURS));

        return $canSend;
    }

    /**
     * Variant for the chat hot path: checks the cached flag by id FIRST, so a
     * cache hit costs no query at all. Only on a miss is the user loaded.
     * An unknown id is allowed.
     */
    public function canSendMessagesById(int $chatterUserId): bool
    {
        $cached = Cache::get($this->cacheKey($chatterUserId));

        if ($cached !== null) {
            return (bool) $cached;
        }

        $chatter = User::find($chatterUserId);

        return $chatter === null || $this->canSendMessages($chatter);
    }

    /** Whether the chatter has used up this month's allowance. */
    public function limitReached(User $chatter): bool
    {
        return ! $this->canSendMessages($chatter);
    }

    /**
     * The configured monthly cap, or null when the chatter has no limit. Read
     * from the latest row, so it is also right between two months.
     */
    public function currentLimit(User $chatter): ?int
    {
        return $chatter->isChatter() ? $this->latestCredit($chatter)?->messages : null;
    }

    /** Messages sent this month (0 between periods or without a limit). */
    public function usedThisPeriod(User $chatter): int
    {
        return $chatter->isChatter()
            ? (int) ($this->currentCredit($chatter)?->usage?->messages ?? 0)
            : 0;
    }

    /**
     * The chatter's credit for the current month (`ends_at` not yet past), with
     * its usage loaded, or null.
     */
    public function currentCredit(User $chatter): ?UserCredit
    {
        return UserCredit::query()
            ->where('user_id', $chatter->id)
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->latest('ends_at')
            ->first();
    }

    /**
     * The chatter's most recent credit, whatever its period — where the cap
     * carries forward from when a new month opens.
     */
    public function latestCredit(User $chatter): ?UserCredit
    {
        return UserCredit::query()
            ->where('user_id', $chatter->id)
            ->latest('ends_at')
            ->first();
    }

    /** Drop the cached send flag so the next check reads the database. */
    public function forget(User $chatter): void
    {
        Cache::forget($this->cacheKey($chatter->id));
    }

    /**
     * Database truth: has the chatter hit this month's cap? No current row
     * counts as "not used up".
     */
    private function capExhausted(User $chatter): bool
    {
        $credit = $this->currentCredit($chatter);

        return $credit !== null && (int) ($credit->usage?->messages ?? 0) >= $credit->messages;
    }

    private function cacheKey(int $chatterId): string
    {
        return template(self::CAN_SEND_CACHE_KEY, ['id' => $chatterId]);
    }
}
