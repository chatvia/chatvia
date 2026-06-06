<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Facades\Cache;

/**
 * Per-chatter monthly MESSAGE limit, stored on the chatter's OWN `user_credits`
 * rows (one per calendar month: `messages` = the cap, `[started_at, ends_at]` =
 * the window) with `user_usages.messages` as the consumed counter.
 *
 * Deliberately separate from {@see UserCreditSystem}, the workspace OWNER's pool
 * resolved through `workspace()`. A chatter cannot reuse that (it would resolve
 * the owner's pool), so this works directly off the chatter's own rows. Every
 * method is a no-op / neutral for non-chatters.
 *
 * No limit == no credit row. Setting a limit upserts the current-period row;
 * clearing deletes the chatter's rows. The window renews lazily on the first
 * stored message of a new month, carrying the cap forward — no scheduler.
 *
 * The send gate ({@see canSendMessages}) is cached as a simple yes/no flag per
 * chatter (mirrors the owner's UserCreditSystem). The flag is invalidated only at
 * the three state transitions — the admin sets/clears the cap, a new month's
 * window is provisioned, or a message exhausts the cap — so ordinary sub-cap
 * sends never hit the DB on the gate path.
 */
class ChatterMessageAllowance
{
    private const string CAN_SEND_CACHE_PREFIX = 'chatter:{id}:can-send-messages';

    /** Backstop TTL only; the flag is invalidated explicitly on every state change. */
    private const int CACHE_TTL_HOURS = 6;

    /**
     * Cached yes/no: may this chatter still send this month? Always true for
     * non-chatters and for chatters with no configured limit. Serves a per-chatter
     * flag and only touches the DB on a cache miss.
     */
    public function canSendMessages(User $chatter): bool
    {
        if (! $chatter->isChatter()) {
            return true;
        }

        $cached = Cache::get($this->cacheKey($chatter));

        if ($cached !== null) {
            return (bool) $cached;
        }

        $canSend = ! $this->capExhausted($chatter);

        Cache::put($this->cacheKey($chatter), $canSend, now()->addHours(self::CACHE_TTL_HOURS));

        return $canSend;
    }

    /**
     * Gate variant for the chat hot path: checks the cached flag by id FIRST, so a
     * cache hit costs zero DB queries (no User load at all). Only on a miss is the
     * user resolved to compute + cache the flag. Unknown id → allowed.
     */
    public function canSendMessagesById(int $chatterUserId): bool
    {
        $cached = Cache::get(template(self::CAN_SEND_CACHE_PREFIX, ['id' => $chatterUserId]));

        if ($cached !== null) {
            return (bool) $cached;
        }

        $chatter = User::find($chatterUserId);

        return $chatter === null || $this->canSendMessages($chatter);
    }

    /**
     * Whether the chatter has spent this month's message allowance. The inverse of
     * {@see canSendMessages}; an absent or expired credit counts as "not reached"
     * (no limit, or a fresh month the write path provisions on the next message).
     */
    public function limitReached(User $chatter): bool
    {
        return ! $this->canSendMessages($chatter);
    }

    /**
     * The configured monthly cap, or null when the chatter has no limit. Carries
     * forward from the latest row so it reads correctly between periods.
     */
    public function currentLimit(User $chatter): ?int
    {
        if (! $chatter->isChatter()) {
            return null;
        }

        return $this->latestCredit($chatter)?->messages;
    }

    /**
     * Messages consumed in the current month (0 when between periods or unset).
     */
    public function usedThisPeriod(User $chatter): int
    {
        if (! $chatter->isChatter()) {
            return 0;
        }

        return (int) ($this->currentCredit($chatter)?->usage?->messages ?? 0);
    }

    /**
     * Count one message, renewing the monthly window first if it rolled over
     * (carrying the cap forward). No-op when the chatter has no limit. Called
     * from the message-persist path ({@see \Domain\Conversation\Actions\Messages\StoreMessageAction}).
     */
    public function recordMessage(User $chatter): void
    {
        if (! $chatter->isChatter()) {
            return;
        }

        $credit = $this->ensureCurrentCredit($chatter);

        if (! $credit) {
            return;
        }

        $credit->usage()->increment('messages', 1);

        // Flip the cached gate to "blocked" the moment this send exhausts the cap.
        // Below the cap we leave the flag alone, so steady-state sends stay cached.
        // (A brand-new window was already invalidated in ensureCurrentCredit.)
        if ((int) ($credit->usage?->messages ?? 0) + 1 >= $credit->messages) {
            $this->forget($chatter);
        }
    }

    /**
     * Admin set/clear of the member's monthly message cap. A null limit clears
     * the row (→ unlimited); otherwise the cap is upserted on the current-period
     * row, preserving usage already consumed this month. Re-caches the gate.
     */
    public function setLimit(User $chatter, ?int $messages): void
    {
        if (! $chatter->isChatter()) {
            return;
        }

        if ($messages === null) {
            UserCredit::query()->where('user_id', $chatter->id)->delete();
            $this->forget($chatter);

            return;
        }

        $this->ensureCurrentCredit($chatter, $messages)?->update(['messages' => $messages]);
        $this->forget($chatter);
    }

    /**
     * The chatter's credit for the current month (`ends_at` not yet past), with
     * its usage eager-loaded, or null.
     */
    private function currentCredit(User $chatter): ?UserCredit
    {
        return UserCredit::query()
            ->where('user_id', $chatter->id)
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->latest('ends_at')
            ->first();
    }

    /**
     * The chatter's most recent credit regardless of period — the source of the
     * configured cap when renewing a rolled-over window.
     */
    private function latestCredit(User $chatter): ?UserCredit
    {
        return UserCredit::query()
            ->where('user_id', $chatter->id)
            ->latest('ends_at')
            ->first();
    }

    /**
     * Return the current-period credit, creating it (with a usage row) when the
     * window has rolled over. The cap comes from $cap when provided (admin set)
     * or carries forward from the latest prior row. Returns null when no cap is
     * configured and none can be derived (chatter has no limit).
     */
    private function ensureCurrentCredit(User $chatter, ?int $cap = null): ?UserCredit
    {
        $current = $this->currentCredit($chatter);

        if ($current) {
            return $current;
        }

        $latest = $this->latestCredit($chatter);
        $cap ??= $latest?->messages;

        if ($cap === null) {
            return null;
        }

        /** @var UserCredit $credit */
        $credit = UserCredit::query()->create([
            'user_id' => $chatter->id,
            'messages' => $cap,
            'started_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $credit->usage()->create();

        // A fresh month's window starts at 0 used — reset the cached gate.
        $this->forget($chatter);

        return $credit->load('usage');
    }

    /**
     * DB truth: has the chatter hit this month's cap? An absent or expired credit
     * row counts as "not exhausted".
     */
    private function capExhausted(User $chatter): bool
    {
        $credit = $this->currentCredit($chatter);

        if (! $credit) {
            return false;
        }

        return (int) ($credit->usage?->messages ?? 0) >= $credit->messages;
    }

    private function cacheKey(User $chatter): string
    {
        return template(self::CAN_SEND_CACHE_PREFIX, ['id' => $chatter->id]);
    }

    private function forget(User $chatter): void
    {
        Cache::forget($this->cacheKey($chatter));
    }
}
