<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The WORKSPACE message-credit pool: capacity enforcement for one account's
 * billing cycle. Scoped to a workspace — the owner `User` row that holds the
 * subscription, credits and add-ons. Resolve it through
 * {@see User::creditSystem()} (which always passes the workspace id) or call
 * {@see forWorkspace()} directly with a workspace-owner id. Chatters/employees
 * share their workspace's pool; the per-chatter sub-allocation is a separate
 * concern handled by {@see ChatterMessageAllowance}.
 *
 * The send gate ({@see canSendMessages}) is a single cached yes/no flag that
 * encodes BOTH halves of "may this workspace send another message": an active
 * billing state (paid subscription or trial) AND remaining capacity this cycle.
 * The subscription half is read from Cashier's locally-synced tables (never a
 * Stripe API call) and only when the flag is recomputed, so the hot path stays
 * one cache read.
 */
class UserCreditSystem
{
    private int $workspaceId;

    private const string CREDIT_CACHE_PREFIX = 'workspace:{id}:credit:current';

    private const string MESSAGES_SENT_CACHE_PREFIX = 'workspace:{id}:credit:messages:sent';

    private const string CAN_SEND_MESSAGES_CACHE_PREFIX = 'workspace:{id}:can-send-messages';

    private const string MESSAGES_AVAILABLE_CACHE_PREFIX = 'workspace:{id}:credit:messages:available';

    /**
     * Scope this instance to a workspace, identified by its owner `User` id.
     */
    public function forWorkspace(int $workspaceId): static
    {
        $this->workspaceId = $workspaceId;

        return $this;
    }

    /**
     * Add credits for a new billing cycle.
     * This method RESETS all existing active credits before adding new ones.
     * Each billing cycle starts fresh - credits do NOT accumulate.
     */
    public function addCredit(array $quotaLimit, Carbon $endsAt): UserCredit
    {
        /** @var User $user */
        $user = User::find($this->workspaceId);

        // Reset credits: Expire all existing active credits before adding new ones
        // This ensures each billing cycle starts fresh (non-accumulative)
        $user->credits()
            ->where('ends_at', '>=', now())
            ->update(['ends_at' => now()->subSecond()]);

        // Pick a started_at past any existing row for this user. The
        // (user_id, started_at) unique index would otherwise collide when two
        // grants land in the same second (e.g. plan-upgrade webhooks fired
        // back-to-back).
        $lastStartedAt = $user->credits()->max('started_at');
        $startedAt = $lastStartedAt ? max(now(), Carbon::parse($lastStartedAt)->addSecond()) : now();

        /** @var UserCredit $credit */
        $credit = $user->credits()->create([
            'started_at' => $startedAt,
            'messages' => $quotaLimit['messages_limit'],
            'ends_at' => $endsAt,
        ]);

        // Only create usage if it doesn't exist
        if (! $credit->usage()->exists()) {
            $credit->usage()->create();
        }

        Cache::put(
            key: template(self::CREDIT_CACHE_PREFIX, ['id' => $this->workspaceId]),
            value: [
                'started_at' => $startedAt,
                'ends_at' => $endsAt,
            ],
            ttl: $endsAt,
        );

        Cache::put(template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->workspaceId]), 0, $endsAt);

        // Cache available messages and set can-send flag
        $this->refreshCanSendMessages();

        return $credit;
    }

    /**
     * Check if user can send messages (fast cached check).
     */
    public function canSendMessages(): bool
    {
        $cached = Cache::get(template(self::CAN_SEND_MESSAGES_CACHE_PREFIX, ['id' => $this->workspaceId]));

        // If not cached, calculate and cache it
        if ($cached === null) {
            return $this->refreshCanSendMessages();
        }

        return $cached;
    }

    /**
     * Set the can-send-messages flag in cache.
     */
    public function setCanSendMessages(bool $canSend, ?Carbon $ttl = null): void
    {
        $ttl = $ttl ?? now()->addDay();
        $cacheId = template(self::CAN_SEND_MESSAGES_CACHE_PREFIX, ['id' => $this->workspaceId]);

        Cache::put(
            $cacheId,
            $canSend,
            $ttl
        );
    }

    /**
     * Refresh the can-send-messages flag by checking database.
     *
     * The gate is two-factor: the workspace must have an active billing state
     * (paid subscription or trial) AND remaining capacity this cycle. Both are
     * folded into the one cached flag so {@see canSendMessages} — the hot path
     * every chat turn hits — never recomputes either half. An unsubscribed
     * workspace sitting on leftover credits therefore can't slip a message
     * through any gate that reads the flag (the eval runner, the capacity
     * middleware, the chat stream, channel replies).
     */
    public function refreshCanSendMessages(): bool
    {
        // Always refresh the available-messages cache (UI reads it), then AND it
        // with the billing state for the gate decision.
        $hasCapacity = $this->refreshAvailableMessages() > 0;

        $canSend = $hasCapacity && $this->workspaceHasActiveBilling();

        $this->setCanSendMessages($canSend, now()->addHours(3));

        return $canSend;
    }

    /**
     * Whether the workspace has an active billing state — a paid subscription or
     * an in-progress trial. Reads Cashier's locally-synced subscription tables
     * via {@see User::hasActiveSubscription()} (a DB read, never a Stripe API
     * call), and only on a gate recompute (cache miss, post-consumption, a
     * subscription webhook's refresh, or the 3h backstop) — never on the cached
     * hot path.
     */
    private function workspaceHasActiveBilling(): bool
    {
        return User::find($this->workspaceId)?->hasActiveSubscription() ?? false;
    }

    /**
     * Refresh the available messages cache from database.
     */
    public function refreshAvailableMessages(): int
    {
        $available = $this->calculateAvailableMessages();

        $cacheId = template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->workspaceId]);

        Cache::put(
            $cacheId,
            $available,
            now()->addHour()
        );

        return $available;
    }

    /**
     * Get available messages from cache or calculate.
     */
    public function getAvailableMessages(): int
    {
        $cached = Cache::get(template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->workspaceId]));

        if ($cached !== null) {
            return max(0, (int) $cached);
        }

        return $this->refreshAvailableMessages();
    }

    /**
     * Update cache after consuming messages.
     * Called by UpdateUserCreditsJob after successful consumption.
     *
     * @param  int  $messagesUsed  Number of messages consumed (based on LLM credits multiplier)
     */
    public function updateAfterCreditConsumption(int $messagesUsed = 1): void
    {
        // Increment sent messages counter
        Cache::increment(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->workspaceId]),
            value: $messagesUsed
        );

        // Decrement available messages cache
        Cache::decrement(
            key: template(self::MESSAGES_AVAILABLE_CACHE_PREFIX, ['id' => $this->workspaceId]),
            value: $messagesUsed
        );
    }

    public function getTotalSentMessages(): int
    {
        $cached = Cache::get(template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->workspaceId]));

        if ($cached !== null) {
            return (int) $cached;
        }

        return $this->refreshSentMessages();
    }

    /**
     * Rebuild the sent-messages counter from the database (the active cycle's
     * usage row — incremented in lockstep with this cache key by
     * ConsumeMessageCreditsAction). Covers cache flush/expiry mid-cycle, which
     * would otherwise show 0 sent on the usage dashboard until the next cycle.
     */
    public function refreshSentMessages(): int
    {
        /** @var User|null $user */
        $user = User::find($this->workspaceId);

        /** @var UserCredit|null $credit */
        $credit = $user?->credits()
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->first();

        $sent = ($credit?->usage?->messages ?? 0);

        Cache::put(
            key: template(self::MESSAGES_SENT_CACHE_PREFIX, ['id' => $this->workspaceId]),
            value: $sent,
            ttl: $credit?->ends_at ?? now()->addHour(),
        );

        return $sent;
    }

    public function consumableMessages(bool $refresh = false): int
    {
        if ($refresh) {
            return $this->refreshAvailableMessages();
        }

        return $this->getAvailableMessages();
    }

    /**
     * Calculate available messages from database.
     *
     * Returns the remaining quota for the workspace's active billing period
     * (plan limit minus consumed usage), plus the sum of active addons.
     * Clamped at 0 when usage has overrun the limit. This is the pure capacity
     * figure — the subscription check lives in the send gate, not here.
     */
    private function calculateAvailableMessages(): int
    {
        /** @var User|null $user */
        $user = User::find($this->workspaceId);

        if (! $user) {
            return 0;
        }

        /** @var UserCredit|null $credit */
        $credit = $user->credits()
            ->where('ends_at', '>=', now())
            ->with('usage')
            ->first();

        if (! $credit) {
            return 0;
        }

        $remaining = max(0, $credit->messages - ($credit->usage?->messages ?? 0));

        $addons = (int) $user->addons()
            ->whereIn('type', ['extra_message_credits', 'auto_recharge_credits'])
            ->where('ends_at', '>=', now())
            ->sum('amount');

        return $remaining + $addons;
    }

    /**
     * Record token consumption for ANALYTICS only: increment the active cycle's
     * usage `tokens` accumulator (real prompt+completion from the provider, with
     * a TikToken estimate as fallback). Tokens never gate or bill — that's
     * messages — this is purely the "tokens consumed this cycle" figure shown
     * on the usage dashboard.
     */
    public function recordTokenUsage(int $tokens): void
    {
        if ($tokens <= 0) {
            return;
        }

        /** @var User|null $user */
        $user = User::find($this->workspaceId);

        /** @var UserCredit|null $credit */
        $credit = $user?->credits()
            ->where('ends_at', '>=', now())
            ->first();

        $credit?->usage()->increment('tokens', $tokens);
    }
}
