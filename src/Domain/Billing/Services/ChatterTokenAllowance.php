<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserToken;

/**
 * Per-chatter monthly TOKEN budget, stored on the chatter's OWN `user_tokens`
 * rows (one per calendar month: `tokens` = the budget, `[started_at, ends_at]` =
 * the window) with `user_usages.tokens` as the consumed accumulator.
 *
 * Deliberately separate from {@see UserTokenSystem}, the workspace OWNER's pool
 * resolved through {@see User::workspace()}. A chatter cannot reuse that (it
 * would resolve the owner's pool), so this works directly off the chatter's own
 * rows. Every method is a no-op / neutral for non-chatters, so an owner's pool
 * can never be read as a chatter cap.
 *
 * No budget == no token row. Setting a budget upserts the current-period row;
 * clearing deletes the chatter's rows (→ unlimited; only the workspace pool then
 * gates). The window renews lazily on the first consumed tokens of a new month,
 * carrying the budget forward — no scheduler.
 */
class ChatterTokenAllowance
{
    /**
     * Whether the chatter has spent this month's token budget. Read-only: an
     * absent or expired row counts as "not reached" (no budget configured, or a
     * fresh month the write path provisions on the next turn).
     */
    public function limitReached(User $chatter): bool
    {
        if (! $chatter->isChatter()) {
            return false;
        }

        $token = $this->currentToken($chatter);

        if (! $token) {
            return false;
        }

        // 0 = no token cap. Billed from the real per-turn token count; the
        // member is blocked once the budget is spent.
        return $token->tokens > 0 && (int) ($token->usage?->tokens ?? 0) >= $token->tokens;
    }

    /**
     * The configured monthly token budget, or null when the chatter has no
     * limit. Carries forward from the latest row so it reads correctly between
     * periods (before the new month's row is provisioned).
     */
    public function tokenBudget(User $chatter): ?int
    {
        if (! $chatter->isChatter()) {
            return null;
        }

        $budget = $this->latestToken($chatter)?->tokens;

        return ($budget ?? 0) > 0 ? $budget : null;
    }

    /**
     * Tokens consumed in the current month (0 when between periods or unset).
     */
    public function tokensUsedThisPeriod(User $chatter): int
    {
        if (! $chatter->isChatter()) {
            return 0;
        }

        return (int) ($this->currentToken($chatter)?->usage?->tokens ?? 0);
    }

    /**
     * Charge the chatter's monthly budget the REAL token count for a turn,
     * renewing the monthly window first if it rolled over (carrying the budget
     * forward). No-op when the chatter has no limit or the count is non-positive.
     * Called from {@see \Domain\Billing\Jobs\RecordTokenUsageJob} alongside the
     * owner charge, from the single real-token source.
     */
    public function recordTokenUsage(User $chatter, int $tokens): void
    {
        if (! $chatter->isChatter() || $tokens <= 0) {
            return;
        }

        $this->ensureCurrentToken($chatter)?->usage()->increment('tokens', $tokens);
    }

    /**
     * Admin set/clear of the member's monthly token budget. A null budget clears
     * the row (→ unlimited; the workspace pool still gates). Otherwise the budget
     * is upserted on the current-period row, preserving usage already consumed
     * this month.
     */
    public function setLimit(User $chatter, ?int $tokens): void
    {
        if (! $chatter->isChatter()) {
            return;
        }

        if ($tokens === null) {
            UserToken::query()->where('user_id', $chatter->id)->delete();

            return;
        }

        $this->ensureCurrentToken($chatter, $tokens)?->update(['tokens' => $tokens]);
    }

    /**
     * The chatter's token row for the current month (`ends_at` not yet past),
     * with its usage eager-loaded, or null.
     */
    private function currentToken(User $chatter): ?UserToken
    {
        return UserToken::query()
            ->where('user_id', $chatter->id)
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->latest('ends_at')
            ->first();
    }

    /**
     * The chatter's most recent token row regardless of period — the source of
     * the configured budget when renewing a rolled-over window.
     */
    private function latestToken(User $chatter): ?UserToken
    {
        return UserToken::query()
            ->where('user_id', $chatter->id)
            ->latest('ends_at')
            ->first();
    }

    /**
     * Return the current-period token row, creating it (with a usage row) when
     * the window has rolled over. The budget comes from $budget when provided
     * (admin set) or carries forward from the latest prior row. Returns null when
     * no budget is configured and none can be derived (chatter has no limit).
     */
    private function ensureCurrentToken(User $chatter, ?int $budget = null): ?UserToken
    {
        $current = $this->currentToken($chatter);

        if ($current) {
            return $current;
        }

        $latest = $this->latestToken($chatter);
        $budget ??= $latest?->tokens;

        if ($budget === null) {
            return null;
        }

        /** @var UserToken $token */
        $token = UserToken::query()->create([
            'user_id' => $chatter->id,
            'tokens' => $budget,
            'started_at' => now()->startOfMonth(),
            'ends_at' => now()->endOfMonth(),
        ]);

        $token->usage()->create();

        return $token->load('usage');
    }
}
