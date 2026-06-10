<?php

namespace Domain\Channel\Teams\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies that an inbound activity really came from the Bot Framework and is
 * addressed to this bot. Unlike the HMAC channels (Slack, Meta) there is no
 * shared signing secret: the connector sends a Bearer JWT signed with
 * Microsoft's published keys. A request is genuine when the signature checks
 * out against the Bot Framework JWKS (cached for a day), the issuer is the
 * connector, the audience is this bot's Microsoft App ID, and — defence in
 * depth — the token's `serviceurl` claim matches the activity's serviceUrl,
 * so a replayed token can't redirect replies elsewhere.
 */
class TeamsActivityVerifier
{
    private const OPENID_CONFIGURATION = 'https://login.botframework.com/v1/.well-known/openidconfiguration';

    private const ISSUER = 'https://api.botframework.com';

    /** Accept this much clock skew on exp/nbf (mirrors the HMAC channels' replay window). */
    private const LEEWAY_SECONDS = 300;

    public function verify(?string $authorization, string $appId, ?string $serviceUrl = null): bool
    {
        if (! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            return false;
        }

        try {
            JWT::$leeway = self::LEEWAY_SECONDS;
            $claims = (array) JWT::decode(substr($authorization, 7), $this->signingKeys());
        } catch (Throwable) {
            return false;
        }

        if (($claims['iss'] ?? null) !== self::ISSUER || ($claims['aud'] ?? null) !== $appId) {
            return false;
        }

        if ($serviceUrl !== null && isset($claims['serviceurl'])
            && rtrim((string) $claims['serviceurl'], '/') !== rtrim($serviceUrl, '/')) {
            return false;
        }

        return true;
    }

    /**
     * The Bot Framework signing keys, resolved via OpenID discovery. The JWKS
     * rotates rarely; the raw document is cached for a day. A fetch failure
     * throws — the caller treats that as "not verified".
     *
     * @return array<string, Key>
     */
    private function signingKeys(): array
    {
        $jwks = Cache::remember('msteams:jwks', now()->addDay(), function (): array {
            $metadata = Http::get(self::OPENID_CONFIGURATION)->throw()->json();

            return Http::get($metadata['jwks_uri'])->throw()->json();
        });

        return JWK::parseKeySet($jwks, 'RS256');
    }
}
