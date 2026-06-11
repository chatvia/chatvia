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
 * connector, and the audience is this bot's Microsoft App ID. Defence in
 * depth: {@see matchesServiceUrl} then checks the verified token's
 * `serviceurl` claim against the activity's serviceUrl, so a replayed token
 * can't redirect replies elsewhere — split off `verify()` so the caller can
 * authenticate the request before parsing its body.
 */
class TeamsActivityVerifier
{
    private const OPENID_CONFIGURATION = 'https://login.botframework.com/v1/.well-known/openidconfiguration';

    private const ISSUER = 'https://api.botframework.com';

    /** Accept this much clock skew on exp/nbf (mirrors the HMAC channels' replay window). */
    private const LEEWAY_SECONDS = 300;

    /**
     * @return array<string, mixed>|null the verified token claims, or null when the request is not genuine
     */
    public function verify(?string $authorization, string $appId): ?array
    {
        if (! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        try {
            JWT::$leeway = self::LEEWAY_SECONDS;
            $claims = (array) JWT::decode(substr($authorization, 7), $this->signingKeys());
        } catch (Throwable) {
            return null;
        }

        if (($claims['iss'] ?? null) !== self::ISSUER || ($claims['aud'] ?? null) !== $appId) {
            return null;
        }

        return $claims;
    }

    /**
     * When the verified token carries a `serviceurl` claim it must match the
     * activity's serviceUrl, so a replayed token can't redirect replies.
     *
     * @param  array<string, mixed>  $claims
     */
    public function matchesServiceUrl(array $claims, string $serviceUrl): bool
    {
        if (! isset($claims['serviceurl'])) {
            return true;
        }

        return rtrim((string) $claims['serviceurl'], '/') === rtrim($serviceUrl, '/');
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
