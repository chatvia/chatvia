<?php

namespace Domain\Channel\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

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
}
