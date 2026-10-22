<?php

namespace Domain\Channel\Teams\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
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
    private const string OPENID_CONFIGURATION = 'https://login.botframework.com/v1/.well-known/openidconfiguration';

    private const string ISSUER = 'https://api.botframework.com';

    /** Accept this much clock skew on exp/nbf (mirrors the HMAC channels' replay window). */
    private const int LEEWAY_SECONDS = 300;

    private const int FETCH_TIMEOUT_SECONDS = 5;

    private const string JWKS_CACHE_KEY = 'msteams:jwks';

    private const string JWKS_REFRESHED_KEY = 'msteams:jwks:refreshed';

    /**
     * @return array<string, mixed>|null the verified token claims, or null when the request is not genuine
     */
    public function verify(?string $authorization, string $appId): ?array
    {
        if (blank($authorization) || ! Str::startsWith($authorization, 'Bearer ')) {
            return null;
        }

        // JWT::$leeway is process-wide. A queue worker or long-running process
        // also verifies other JWTs, so widen it only for this decode and put the
        // previous value back afterwards, even when the decode throws.
        $previousLeeway = JWT::$leeway;

        try {
            JWT::$leeway = self::LEEWAY_SECONDS;
            $claims = $this->decode(Str::after($authorization, 'Bearer '));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        if (($claims['iss'] ?? null) !== self::ISSUER || ($claims['aud'] ?? null) !== $appId) {
            return null;
        }

        return $claims;
    }

    /**
     * The verified token's `serviceurl` claim must match the activity's
     * serviceUrl, so a replayed token can't redirect replies. The Bot Framework
     * requires the claim on every connector token, so a token without it is
     * refused, and so is a service URL that is not https: the bot's token is
     * sent there with every reply.
     *
     * @param  array<string, mixed>  $claims
     */
    public function matchesServiceUrl(array $claims, string $serviceUrl): bool
    {
        $claimed = $claims['serviceurl'] ?? null;

        if (blank($claimed) || ! Str::startsWith($serviceUrl, 'https://')) {
            return false;
        }

        return Str::rtrim((string) $claimed, '/') === Str::rtrim($serviceUrl, '/');
    }

    /**
     * Decode against the cached keys. A token signed with a key the cache does
     * not hold means Microsoft rotated its keys, so they are fetched again once.
     * The refetch runs at most every ten minutes, so forged tokens with made-up
     * key ids cannot turn every request into a call to Microsoft.
     *
     * @return array<string, mixed>
     */
    private function decode(string $jwt): array
    {
        $keys = $this->signingKeys();
        $keyId = $this->keyId($jwt);

        if ($keyId !== null && ! isset($keys[$keyId]) && Cache::add(self::JWKS_REFRESHED_KEY, true, now()->addMinutes(10))) {
            Cache::forget(self::JWKS_CACHE_KEY);
            $keys = $this->signingKeys();
        }

        return (array) JWT::decode($jwt, $keys);
    }

    /** The `kid` in the token's header, or null when the header can't be read. */
    private function keyId(string $jwt): ?string
    {
        return rescue(function () use ($jwt): ?string {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode(Str::before($jwt, '.')));

            return filled($header->kid ?? null) ? (string) $header->kid : null;
        }, null, report: false);
    }

    /**
     * The Bot Framework signing keys, resolved via OpenID discovery. The JWKS
     * rotates rarely; the raw document is cached for a day. A fetch failure
     * throws — the caller treats that as "not verified". Each fetch is short:
     * it runs inside the webhook, which Microsoft gives 15 seconds.
     *
     * @return array<string, Key>
     */
    private function signingKeys(): array
    {
        $jwks = Cache::remember(self::JWKS_CACHE_KEY, now()->addDay(), function (): array {
            $metadata = Http::timeout(self::FETCH_TIMEOUT_SECONDS)->get(self::OPENID_CONFIGURATION)->throw()->json();

            return Http::timeout(self::FETCH_TIMEOUT_SECONDS)->get($metadata['jwks_uri'])->throw()->json();
        });

        return JWK::parseKeySet($jwks, 'RS256');
    }
}
