<?php

namespace Domain\Channel\Contracts;

use Domain\Channel\Services\Replies\StreamingReplyStrategy;

/**
 * A channel whose messages can be edited in place (Slack, Microsoft Teams), so
 * the reply really streams: {@see StreamingReplyStrategy} posts a placeholder
 * and rewrites it with the text so far, throttled to the platform's edit rate
 * limit.
 */
interface StreamsReplies extends ChannelAdapter
{
    /**
     * Post a message and return its platform id — the handle later edits
     * stream into — or null when posting failed.
     */
    public function postMessage(string $markdown): ?string;

    /** Rewrite a posted message in place. */
    public function updateMessage(string $messageId, string $markdown): bool;

    /** Push an edit at most this often, in seconds. */
    public function streamThrottleSeconds(): float;
}
