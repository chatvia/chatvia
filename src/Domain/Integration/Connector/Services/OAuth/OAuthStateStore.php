<?php

namespace Domain\Integration\Connector\Services\OAuth;

use Domain\Integration\Connector\Data\OAuth\PendingOAuthState;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keeps an in-flight connector OAuth flow between the authorize redirect and
 * the callback, keyed by the random `state` parameter. Stored encrypted (the
 * state carries the PKCE verifier and sometimes a client secret) for ten
 * minutes — long enough for slow consent screens, short enough to bound the
 * CSRF window. A state can be read once: {@see pull()} removes it.
 */
class OAuthStateStore
{
    private const int TTL_SECONDS = 600;

    private const int STATE_LENGTH = 48;

    /**
     * Store the pending flow and return the `state` value that identifies it.
     */
    public function put(PendingOAuthState $pending): string
    {
        $state = Str::random(self::STATE_LENGTH);

        $this->store()->put($this->key($state), $pending->seal(), self::TTL_SECONDS);

        return $state;
    }

    /**
     * The pending flow for this state, removed from the store; null when it
     * expired, was already used or never existed.
     */
    public function pull(string $state): ?PendingOAuthState
    {
        return PendingOAuthState::unseal($this->store()->pull($this->key($state)));
    }

    private function key(string $state): string
    {
        return 'oauth:mcp:state:'.$state;
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.default'));
    }
}
