<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The workspace owner's token pool — the single credit currency. Tokens are the
 * only send-gate ("balance > 0"); messages are display/analytics only. Keyed by
 * {@see setUserId()} and resolved through {@see User::tokenSystem()}.
 */
class UserTokenSystem
{
    private int $userId;

    private const string TOKENS_AVAILABLE_CACHE_PREFIX = 'user:{id}:token:available';

    public function setUserId(int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Grant a fresh token budget for a new billing cycle. RESETS first: every
     * active grant is expired before the new row is created, so the base
     * allowance never accumulates across cycles. The budget comes straight from
     * the plan (config billing.packages.*.quota_limits.*.tokens_limit).
     */
    public function grant(array $quotaLimit, Carbon $endsAt): UserToken
    {
        /** @var User $user */
        $user = User::find($this->userId);

        // Non-accumulative: expire any active grant before adding the new one.
        $user->tokens()
            ->where('ends_at', '>=', now())
            ->update(['ends_at' => now()->subSecond()]);

        // Pick a started_at past any existing row — the (user_id, started_at)
        // unique index would otherwise collide when two grants land in the same
        // second (e.g. back-to-back plan-upgrade webhooks).
        $lastStartedAt = $user->tokens()->max('started_at');
        $startedAt = $lastStartedAt ? max(now(), Carbon::parse($lastStartedAt)->addSecond()) : now();

        /** @var UserToken $token */
        $token = $user->tokens()->create([
            'started_at' => $startedAt,
            'tokens' => (int) ($quotaLimit['tokens_limit'] ?? 0),
            'ends_at' => $endsAt,
        ]);

        if (! $token->usage()->exists()) {
            $token->usage()->create();
        }

        // Seed the available-tokens cache for the new cycle.
        $this->refreshAvailableTokens();

        return $token;
    }

    /**
     * Available tokens for the active cycle (budget − consumed + active add-on
     * packs), clamped at 0. Cached; recomputed from the database on a miss.
     */
    public function getAvailableTokens(): int
    {
        $cached = Cache::get(template(self::TOKENS_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]));

        if ($cached !== null) {
            return max(0, (int) $cached);
        }

        return $this->refreshAvailableTokens();
    }

    /**
     * Recompute available tokens from the database and cache the result.
     * Authoritative — used both to seed the cache on grant and to correct it
     * after each consumption (avoids drift from raw cache decrements).
     */
    public function refreshAvailableTokens(): int
    {
        $available = $this->calculateAvailableTokens();

        Cache::put(
            template(self::TOKENS_AVAILABLE_CACHE_PREFIX, ['id' => $this->userId]),
            $available,
            now()->addHour(),
        );

        return $available;
    }

    /**
     * The PRIMARY send-gate: does the active cycle still hold a positive token
     * balance? Even 1 token sends. No active grant → blocked. The turn that
     * crosses zero still completes (gate checked before, consumption recorded
     * after) and overshoots; this returns false on the next turn.
     */
    public function hasTokenBalance(): bool
    {
        return $this->getAvailableTokens() > 0;
    }

    /**
     * Record real token consumption for the active cycle: atomically add to the
     * usage accumulator (a relative UPDATE — never depends on a stale read and
     * needs no row lock), then recompute the cached balance. The accumulator is
     * ALLOWED to exceed the budget (soft overage); the balance clamps at 0.
     *
     * Idempotency for retried jobs lives in the caller
     * ({@see \Domain\Billing\Jobs\RecordTokenUsageJob}) via the token_charges
     * ledger — this method just applies the delta.
     */
    public function recordTokenUsage(int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }

        /** @var UserToken|null $token */
        $token = User::find($this->userId)?->tokens()
            ->where('ends_at', '>=', now())
            ->first();

        if (! $token) {
            return;
        }

        $token->usage()->firstOrCreate([]);
        $token->usage()->increment('tokens', $tokens);

        $this->refreshAvailableTokens();
    }

    /**
     * Available tokens from the database: active cycle budget − consumed + active
     * add-on packs, clamped at 0.
     *
     * Token-primary: "no active billing cycle" must BLOCK (return 0). An active
     * grant with a 0 budget means "unlimited" ({@see PHP_INT_MAX}) so a plan that
     * deliberately sets no cap isn't blocked. Add-on packs (extra_token_credits /
     * auto_recharge_tokens) expire 30 days after purchase and are pooled into the
     * balance while active.
     */
    private function calculateAvailableTokens(): int
    {
        /** @var User|null $user */
        $user = User::find($this->userId);

        if (! $user) {
            return 0;
        }

        /** @var UserToken|null $token */
        $token = $user->tokens()
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->first();

        if (! $token) {
            return 0;
        }

        if ($token->tokens === 0) {
            return PHP_INT_MAX;
        }

        $addonTokens = (int) $user->addons()
            ->whereIn('type', ['extra_token_credits', 'auto_recharge_tokens'])
            ->where('ends_at', '>=', now())
            ->sum('amount');

        return max(0, $token->tokens + $addonTokens - ($token->usage?->tokens ?? 0));
    }
}
