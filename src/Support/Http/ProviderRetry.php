<?php

declare(strict_types=1);

namespace Support\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Retries a call to an AI provider that was turned away for a moment: a rate
 * limit (429) or an overloaded gateway (502, 503, 504). It waits as long as
 * the provider's Retry-After asks (capped), else a short backoff that doubles.
 * A refused key, a bad request or a timeout is not retried. The last answer is
 * returned as the caller would have got it, so error handling stays the same.
 */
final class ProviderRetry
{
    /** Tries in all, the first included. */
    public const int TRIES = 3;

    /** The first backoff, in milliseconds; it doubles after each try. */
    private const int BASE_DELAY_MS = 500;

    /** A Retry-After longer than this is not waited for in full. */
    private const int MAX_DELAY_MS = 10_000;

    private const array STATUSES = [429, 502, 503, 504];

    public static function request(): PendingRequest
    {
        return self::on(Http::createPendingRequest());
    }

    public static function on(PendingRequest $request): PendingRequest
    {
        return $request->retry(
            self::TRIES,
            static fn (int $attempt, Throwable $exception): int => self::delay($attempt, $exception),
            static fn (Throwable $exception): bool => $exception instanceof RequestException && in_array($exception->response->status(), self::STATUSES, true),
            throw: false,
        );
    }

    /** Milliseconds before the next try. */
    public static function delay(int $attempt, Throwable $exception): int
    {
        $retryAfter = $exception instanceof RequestException ? $exception->response->header('Retry-After') : '';

        if (is_numeric($retryAfter)) {
            return (int) min((float) $retryAfter * 1000, self::MAX_DELAY_MS);
        }

        return min(self::BASE_DELAY_MS * (2 ** ($attempt - 1)), self::MAX_DELAY_MS);
    }
}
