<?php

namespace App\Http\Macros;

use Domain\Agent\Services\AgentLocator;
use Domain\Security\Services\NetworkAccessGuard;
use Illuminate\Support\Facades\Request;

/**
 * Request macros the app relies on: the subdomain and registrable domain of
 * the host, the visitor's country, and the agent a request is for.
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
        self::registerHostMacros();
        self::registerCountryMacro();
        self::registerAgentSlugMacro();
    }

    private static function registerHostMacros(): void
    {
        Request::macro('subdomain', function (): ?string {
            $host = $this->getHost();
            $dots = substr_count($host, '.');

            if ($dots === 2 || str_contains($host, '.localhost')) {
                return explode('.', $host)[0];
            }

            return null;
        });

        Request::macro('domain', function () {
            $host = parse_url($this->getSchemeAndHttpHost(), PHP_URL_HOST);

            preg_match('/([a-z0-9\-]+\.[a-z]{2,})$/i', $host, $matches);

            return $matches[1] ?? $host.($this->getPort() === 80 ? '' : ':'.$this->getPort());
        });
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

    /**
     * The agent a request is for: the `?agent=` query, a custom domain mapped
     * to an agent, the agent subdomain, or the agent remembered in the session.
     */
    private static function registerAgentSlugMacro(): void
    {
        Request::macro('agentSlug', function () {
            $agent = $this->query('agent');

            if (is_string($agent) && $agent !== '') {
                return $agent;
            }

            // Custom domain: when the host isn't ours, try the `agents.domain`
            // mapping. The locator checks the owner still has Custom Domain
            // quota, so a cancelled add-on stops serving once its host cache
            // expires.
            $host = $this->getHost();
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            $isOurHost = $host === $appHost
                || ($appHost && str_ends_with($host, '.'.$appHost))
                || str_ends_with($host, '.localhost')
                || $host === 'localhost';

            if (! $isOurHost) {
                $resolved = AgentLocator::resolveByHost($host);

                if ($resolved !== null) {
                    return $resolved;
                }
            }

            $sub = $this->subdomain();

            if ($sub !== null && $sub !== 'www') {
                return $sub;
            }

            return $this->hasSession() ? $this->session()->get('chat_agent') : null;
        });
    }
}
