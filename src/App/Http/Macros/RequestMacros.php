<?php

namespace App\Http\Macros;

use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Support\Facades\Request;
use Support\Http\CloudflareIps;

/**
 * Request macros the app relies on: the visitor's country.
 *
 * `$request->country()` prefers Cloudflare's `CF-IPCountry` header: Cloudflare
 * geolocates the real visitor at the edge. Geolocating the connecting IP
 * instead would return the country of the Cloudflare edge that served the
 * request, not the visitor's. The `CF-*` headers are only believed when the
 * connection demonstrably came through Cloudflare or a proxy the operator
 * trusts (TRUSTED_PROXIES); anyone who reaches the origin directly could
 * otherwise fake a country past the geo rules.
 */
final class RequestMacros
{
    /**
     * Cloudflare's non-country values: unknown and Tor. Public because the
     * macro closures run bound to the request, not to this class.
     */
    public const array CLOUDFLARE_NON_COUNTRIES = ['XX', 'T1'];

    public static function register(): void
    {
        self::registerCountryMacro();
    }

    /**
     * The visitor's ISO-3166 alpha-2 country (lowercase), or null when it can't
     * be resolved (callers treat null as "allow").
     */
    private static function registerCountryMacro(): void
    {
        Request::macro('country', function (): ?string {
            // The `CF-*` headers are believed only when the connecting peer is
            // a Cloudflare edge (so the headers are Cloudflare's own, even on
            // an install that forgot TRUSTED_PROXIES) or a proxy the operator
            // vouches for. A direct connection carrying them is a spoof.
            $cloudflareTrusted = CloudflareIps::contains((string) $this->server->get('REMOTE_ADDR'))
                || $this->isFromTrustedProxy();

            if ($cloudflareTrusted) {
                $cf = strtoupper(trim((string) $this->header('CF-IPCountry')));

                if ($cf !== '' && ! in_array($cf, RequestMacros::CLOUDFLARE_NON_COUNTRIES, true)) {
                    return strtolower($cf);
                }
            }

            // CF-IPCountry is missing or a non-country value (the zone's IP
            // geolocation is off, or the request did not come through
            // Cloudflare). Geolocate the REAL visitor IP, not blindly
            // $this->ip(): behind Cloudflare the connecting IP is the edge,
            // which resolves to the edge's country — CF-Connecting-IP is the
            // visitor. On a connection that did not come through Cloudflare or
            // a trusted proxy that header is untrusted, and $this->ip() is the
            // peer itself.
            $clientIp = $cloudflareTrusted
                ? ((string) $this->header('CF-Connecting-IP') ?: (string) $this->ip())
                : (string) $this->ip();

            $country = app(NetworkAccessGuard::class)->countryFor($clientIp);

            return $country !== null ? strtolower($country) : null;
        });
    }
}
