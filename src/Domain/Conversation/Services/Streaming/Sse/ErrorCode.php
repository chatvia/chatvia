<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Streaming\Sse;

/**
 * Why a streamed turn ended with an error. The chat clients switch on these
 * strings (and on whether a retry can help), so they must not change.
 */
enum ErrorCode: string
{
    case MessageLimitReached = 'message_limit_reached';
    case ChatterMessageLimitReached = 'chatter_message_limit_reached';
    case UpstreamUnavailable = 'upstream_unavailable';
    case RateLimited = 'rate_limited';
    case Internal = 'internal';
    // A workspace admin blocked this visitor (Guardrails → Activity).
    case AccessBlocked = 'access_blocked';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::UpstreamUnavailable, self::RateLimited => true,
            default => false,
        };
    }
}
