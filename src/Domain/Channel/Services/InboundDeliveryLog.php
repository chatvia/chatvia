<?php

namespace Domain\Channel\Services;

use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Remembers which inbound webhook deliveries were already queued. Every
 * provider re-delivers an event it thinks we missed (on a timeout or a non-2xx
 * reply), for minutes up to days, so each inbound handler records the event's
 * own id here and skips a repeat — an agent turn bills the owner, so it must
 * run once.
 */
class InboundDeliveryLog
{
    /**
     * True the first time `$key` is seen before `$until`; false for a
     * re-delivery. `$until` must outlive the provider's retry window.
     */
    public function firstDelivery(string $key, DateTimeInterface $until): bool
    {
        return Cache::add($key, true, $until);
    }

    /**
     * Run `$dispatch` for the first delivery of `$key` only. When it throws
     * (the queue is down), the claim is given back before the error goes on:
     * the webhook then answers with an error, and the provider's retry must be
     * queued, not dropped as a repeat. A null key (an event without an id)
     * cannot be told apart from a repeat, so it always runs.
     *
     * @param  Closure(): void  $dispatch
     */
    public function once(?string $key, DateTimeInterface $until, Closure $dispatch): void
    {
        if ($key !== null && ! $this->firstDelivery($key, $until)) {
            return;
        }

        try {
            $dispatch();
        } catch (Throwable $e) {
            if ($key !== null) {
                Cache::forget($key);
            }

            throw $e;
        }
    }
}
