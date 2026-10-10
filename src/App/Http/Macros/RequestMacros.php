<?php

namespace App\Http\Macros;

use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Support\Facades\Request;

/**
 * Request macros the app relies on: the visitor's country.
 *
 * `$request->country()` trusts Cloudflare's `CF-IPCountry` header: every
 * deployed environment sits behind Cloudflare, which geolocates the real
 * visitor. Geolocating the connecting IP instead would return the country of
 * the Cloudflare edge that served the request, not the visitor's.
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
            $cf = strtoupper(trim((string) $this->header('CF-IPCountry')));

            if ($cf !== '' && ! in_array($cf, RequestMacros::CLOUDFLARE_NON_COUNTRIES, true)) {
                return strtolower($cf);
            }

            // CF-IPCountry is missing or a non-country value (the zone's IP
            // geolocation is off, or the request did not come through
            // Cloudflare). Geolocate the REAL visitor IP, not $this->ip():
            // behind Cloudflare the connecting IP is the edge, which resolves
            // to the edge's country. CF-Connecting-IP is the visitor and can be
            // trusted because the origin only accepts Cloudflare traffic.
            $clientIp = $this->header('CF-Connecting-IP') ?: (string) $this->ip();

            $country = app(NetworkAccessGuard::class)->countryFor($clientIp);

            return $country !== null ? strtolower($country) : null;
        });
    }
}
