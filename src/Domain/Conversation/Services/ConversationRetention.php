<?php

declare(strict_types=1);

namespace Domain\Conversation\Services;

/**
 * How many days conversations are kept on this install, set in the admin
 * panel (Settings → Workspaces). A workspace may choose fewer days in its
 * security policy, never more.
 */
final class ConversationRetention
{
    /** Used while nothing is set: a little over a year. */
    public const int DEFAULT_DAYS = 366;

    public static function days(): int
    {
        return max(1, (int) (config('chatvia.retention.conversations_days') ?? self::DEFAULT_DAYS));
    }
}
