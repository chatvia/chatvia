<?php

namespace Domain\Chat\Services\Streaming\Sse;

enum ErrorCode: string
{
    case SubscriptionExpired = 'subscription_expired';
    case MessageLimitReached = 'message_limit_reached';
    case UpstreamUnavailable = 'upstream_unavailable';
    case RateLimited = 'rate_limited';
    case Internal = 'internal';

    public function isRetryable(): bool
    {
        return match ($this) {
            self::UpstreamUnavailable, self::RateLimited => true,
            default => false,
        };
    }
}
