<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\MessageLimit;
use Illuminate\Support\Facades\Cache;
use Support\License\Entitlements;
use Support\License\FreeTier;

/**
 * Whether an account — a workspace or a chatter — may send
 * one more message this month, from its {@see MessageLimit}. An account
 * without a limit always may. Workspace and chatter ids come from different
 * tables, so each has its own methods and cache keys.
 *
 * Without a license key a workspace also has the free-use cap
 * ({@see Entitlements::monthlyMessageLimit()}) on top of any limit an admin
 * set, so its replies are always counted ({@see countingLimitFor()}).
 *
 * The answer is cached per account and month, so a chat turn usually costs no
 * query, and a new month never reads last month's answer. The free cap is part
 * of the key, so adding or removing a license key takes effect at once.
 * Changing a limit or reaching it drops the cached answer.
 */
class MessageAllowance
{
    private const string CACHE_KEY = 'message-limit:{owner}:{month}';

    private const int CACHE_TTL_HOURS = 6;

    public function __construct(private readonly Entitlements $entitlements) {}

    /** Whether the workspace's agents may send one more reply this month. */
    public function canSend(int $workspaceId): bool
    {
        $cap = $this->entitlements->monthlyMessageLimit();

        return $this->allows($this->workspaceOwner($workspaceId, $cap), fn (): ?MessageLimit => $this->limitFor($workspaceId), $cap);
    }

    /** Whether the chatter may send one more message of their own this month. */
    public function canSendAsChatter(int $chatterId): bool
    {
        return $this->allows("chatter:{$chatterId}", fn (): ?MessageLimit => $this->limitForChatter($chatterId), null);
    }

    public function limitFor(int $workspaceId): ?MessageLimit
    {
        return MessageLimit::query()->where('workspace_id', $workspaceId)->first();
    }

    /**
     * The row a workspace's replies are counted on. Without a license key
     * every workspace gets one (with no limit of its own), so the free-use cap
     * can be checked; with a key, only a workspace an admin limited has one.
     */
    public function countingLimitFor(int $workspaceId): ?MessageLimit
    {
        $limit = $this->limitFor($workspaceId);

        if ($limit instanceof MessageLimit || $this->entitlements->monthlyMessageLimit() === null) {
            return $limit;
        }

        return MessageLimit::query()->createOrFirst(
            ['workspace_id' => $workspaceId],
            ['monthly_limit' => null, 'messages_used' => 0, 'period_starts_at' => now()->startOfMonth()],
        );
    }

    public function limitForChatter(int $chatterId): ?MessageLimit
    {
        return MessageLimit::query()->where('chatter_id', $chatterId)->first();
    }

    /**
     * The limit in force on a row: a workspace's own limit or the free-use
     * cap, whichever is smaller; a chatter's own limit. Null for no limit.
     */
    public function effectiveLimit(MessageLimit $limit): ?int
    {
        return $limit->effectiveLimit($this->capFor($limit));
    }

    /** Whether the row's account has reached the limit in force this month. */
    public function reached(MessageLimit $limit): bool
    {
        return $limit->reached($this->capFor($limit));
    }

    /**
     * The workspace's reply limit in force while it has no row yet: the
     * free-use cap without a license key, otherwise none.
     */
    public function workspaceCap(): ?int
    {
        return $this->entitlements->monthlyMessageLimit();
    }

    public function forget(int $workspaceId): void
    {
        // Both answers: with a license key and without one.
        Cache::forget($this->cacheKey($this->workspaceOwner($workspaceId, null)));
        Cache::forget($this->cacheKey($this->workspaceOwner($workspaceId, FreeTier::MONTHLY_MESSAGES)));
    }

    public function forgetChatter(int $chatterId): void
    {
        Cache::forget($this->cacheKey("chatter:{$chatterId}"));
    }

    /** Drop the cached answer of whichever account owns the limit. */
    public function forgetLimit(MessageLimit $limit): void
    {
        $limit->chatter_id !== null
            ? $this->forgetChatter($limit->chatter_id)
            : $this->forget((int) $limit->workspace_id);
    }

    /** The free-use cap applies to a workspace's row, never to a chatter's. */
    private function capFor(MessageLimit $limit): ?int
    {
        return $limit->workspace_id !== null ? $this->entitlements->monthlyMessageLimit() : null;
    }

    private function workspaceOwner(int $workspaceId, ?int $cap): string
    {
        return "workspace:{$workspaceId}".($cap === null ? '' : ":cap{$cap}");
    }

    /**
     * @param  \Closure(): ?MessageLimit  $limit
     */
    private function allows(string $owner, \Closure $limit, ?int $cap): bool
    {
        return (bool) Cache::remember(
            $this->cacheKey($owner),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): bool => ! ($limit()?->reached($cap) ?? false),
        );
    }

    private function cacheKey(string $owner): string
    {
        return template(self::CACHE_KEY, ['owner' => $owner, 'month' => now()->format('Y-m')]);
    }
}
