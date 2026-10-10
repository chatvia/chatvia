<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\MessageLimit;
use Illuminate\Support\Facades\Cache;

/**
 * Whether an account may send one more message this month, from its
 * {@see MessageLimit}. An account without a limit always may.
 *
 * The answer is cached per account and month, so a chat turn usually costs no
 * query, and a new month never reads last month's answer. Changing a limit or
 * reaching it drops the cached answer.
 */
class MessageAllowance
{
    private const string CACHE_KEY = 'message-limit:{id}:{month}';

    /** Backstop only; the answer is dropped on every change that matters. */
    private const int CACHE_TTL_HOURS = 6;

    public function canSend(int $userId): bool
    {
        return (bool) Cache::remember(
            $this->cacheKey($userId),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): bool => ! ($this->limitFor($userId)?->reached() ?? false),
        );
    }

    public function limitFor(int $userId): ?MessageLimit
    {
        return MessageLimit::query()->where('user_id', $userId)->first();
    }

    /** Drop the cached answer so the next check reads the database. */
    public function forget(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return template(self::CACHE_KEY, ['id' => $userId, 'month' => now()->format('Y-m')]);
    }
}
