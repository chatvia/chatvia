<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Conversation;

use Spatie\LaravelData\Data;

/**
 * Why an API turn ended early ({@see MessageTurnData}). The codes are the
 * same ones the SSE mode sends in its `error` events.
 */
class TurnErrorData extends Data
{
    public function __construct(
        /**
         * One of: `message_limit_reached`, `chatter_message_limit_reached`,
         * `rate_limited`, `upstream_unavailable`, `access_blocked`, `internal`.
         */
        public string $code,
        /**
         * A short human-readable explanation.
         */
        public ?string $message,
        /**
         * Whether sending the same message again can succeed.
         */
        public bool $retryable,
    ) {}
}
