<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Domain\Conversation\Models\Session;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Emitted when a Session arrives without a resolved country/city. A queued
 * listener resolves the location off the message-write hot path so the
 * StoreUserMessageAction never blocks on a third-party geolocation lookup.
 *
 * `$countryCode` is the lowercase ISO alpha-2 resolved at request time
 * (CF-aware via Request::country()) — the trusted country signal the queued
 * listener can't recompute, since it runs without the request. When null the
 * country stays unresolved: the listener never geo-locates the stored IP for
 * country (behind Cloudflare it's the edge PoP, which resolves to the wrong
 * country), it only uses the IP for city/region when one is available.
 */
class SessionLocationUnknown
{
    use Dispatchable;

    public function __construct(
        public readonly Session $session,
        public readonly ?string $countryCode = null,
    ) {}
}
