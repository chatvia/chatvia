<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\MessageLimit;
use Illuminate\Support\Facades\Cache;

/**
 * Whether an account — a workspace or a chatter — may send
 * one more message this month, from its {@see MessageLimit}. An account
 * without a limit always may. Workspace and chatter ids come from different
 * tables, so each has its own methods and cache keys.
 *
 * The answer is cached per account and month, so a chat turn usually costs no
 * query, and a new month never reads last month's answer. Changing a limit or
 * reaching it drops the cached answer.
 */
class MessageAllowance
{
    private const string CACHE_KEY = 'message-limit:{owner}:{month}';

    private const int CACHE_TTL_HOURS = 6;

    /** Whether the workspace's agents may send one more reply this month. */
    public function canSend(int $workspaceId): bool
    {
        return $this->allows('workspace:'.$workspaceId, fn (): ?MessageLimit => $this->limitFor($workspaceId));
    }

    /** Whether the chatter may send one more message of their own this month. */
    public function canSendAsChatter(int $chatterId): bool
    {
        return $this->allows('chatter:'.$chatterId, fn (): ?MessageLimit => $this->limitForChatter($chatterId));
    }

    public function limitFor(int $workspaceId): ?MessageLimit
    {
        return MessageLimit::query()->where('workspace_id', $workspaceId)->first();
    }

    public function limitForChatter(int $chatterId): ?MessageLimit
    {
        return MessageLimit::query()->where('chatter_id', $chatterId)->first();
    }

    public function forget(int $workspaceId): void
    {
        Cache::forget($this->cacheKey('workspace:'.$workspaceId));
    }

    public function forgetChatter(int $chatterId): void
    {
        Cache::forget($this->cacheKey('chatter:'.$chatterId));
    }

    /** Drop the cached answer of whichever account owns the limit. */
    public function forgetLimit(MessageLimit $limit): void
    {
        $limit->chatter_id !== null
            ? $this->forgetChatter($limit->chatter_id)
            : $this->forget((int) $limit->workspace_id);
    }

    /**
     * @param  \Closure(): ?MessageLimit  $limit
     */
    private function allows(string $owner, \Closure $limit): bool
    {
        return (bool) Cache::remember(
            $this->cacheKey($owner),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): bool => ! ($limit()?->reached() ?? false),
        );
    }

    private function cacheKey(string $owner): string
    {
        return template(self::CACHE_KEY, ['owner' => $owner, 'month' => now()->format('Y-m')]);
    }
}
