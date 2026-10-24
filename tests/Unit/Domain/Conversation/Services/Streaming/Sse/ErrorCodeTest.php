<?php

declare(strict_types=1);

use Domain\Conversation\Services\Streaming\Sse\ErrorCode;

/**
 * Which stream errors the chat clients may retry on their own: an unreachable
 * model and a rate limit pass; a used-up limit and an internal error do not.
 */
it('tells which errors a client may retry', function (ErrorCode $code, bool $retryable): void {
    expect($code->isRetryable())->toBe($retryable);
})->with([
    'the model is unreachable' => [ErrorCode::UpstreamUnavailable, true],
    'rate limited' => [ErrorCode::RateLimited, true],
    'the message limit is reached' => [ErrorCode::MessageLimitReached, false],
    'an internal error' => [ErrorCode::Internal, false],
]);
