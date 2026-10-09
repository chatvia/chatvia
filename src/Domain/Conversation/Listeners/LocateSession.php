<?php

namespace Domain\Conversation\Listeners;

use Domain\Analytics\Data\IpLocationData;
use Domain\Analytics\Services\IP2Country;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Session;
use Illuminate\Contracts\Queue\ShouldQueue;
use Support\Localization\Country\Models\Country;

/**
 * Resolves a visitor session's country (and, when the IP agrees, its city,
 * region and time zone) off the message-write path. Queued.
 */
class LocateSession implements ShouldQueue
{
    public function __construct(
        private readonly IP2Country $ip2Country,
    ) {}

    public function handle(SessionLocationUnknown $event): void
    {
        $session = $event->session->fresh();
        if (! $session || $session->country_id) {
            return;
        }

        // Country comes ONLY from the request-time signal (Request::country() —
        // Cloudflare's CF-IPCountry, else a geo lookup of the real visitor IP).
        // Never geo-locate the stored connecting IP for country: behind
        // Cloudflare that IP is the edge PoP and resolves to the wrong country
        // (e.g. Bulgaria for Middle-East traffic). With no trusted country we
        // leave country_id null rather than stamp the PoP's country.
        $countryCode = $event->countryCode;
        if (! $countryCode) {
            return;
        }

        // IP geo is best-effort enrichment for city/region/timezone only, and
        // only when it agrees with the trusted country (see stamp()).
        $location = $session->ip_address ? $this->ip2Country->locate($session->ip_address) : null;

        $this->stamp($session, strtolower($countryCode), $location);
    }

    private function stamp(Session $session, string $countryCode, ?IpLocationData $location): void
    {
        $updates = [];

        $country = Country::query()->where('code', $countryCode)->first();
        if ($country) {
            $updates['country_id'] = $country->id;
        }

        // City/region/timezone/zip come only from the IP lookup. When the IP
        // resolves to a different country than the trusted one, those fields are
        // for the wrong place (the CF edge PoP) — drop them rather than stamp a
        // city that contradicts the country.
        $ipMatchesCountry = $location?->countryCode
            && strtolower($location->countryCode) === $countryCode;

        if ($ipMatchesCountry) {
            if (! $session->city && $location->cityName) {
                $updates['city'] = $location->cityName;
            }

            if ($location->regionName) {
                $updates['region'] = $location->regionName;
            }

            if ($location->zipCode) {
                $updates['zip_code'] = $location->zipCode;
            }

            if ($location->timeZone) {
                $updates['time_zone'] = $location->timeZone;
            }
        }

        // Proxy detection is about the connecting IP itself, independent of the
        // geo mismatch — keep it whenever the lookup returned a verdict.
        if ($location instanceof IpLocationData && $location->isProxy !== null) {
            $updates['is_proxy'] = $location->isProxy;
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }
}
