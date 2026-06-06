<?php

namespace Domain\Conversation\Events;

use Domain\Conversation\Models\Session;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when a Session arrives without a resolved country/city. A queued
 * listener resolves the location off the message-write hot path so the
 * StoreMessageAction never blocks on a third-party geolocation lookup.
 */
class SessionLocationUnknown
{
    use Dispatchable;

    public function __construct(public readonly Session $session) {}
}
