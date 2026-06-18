<?php

namespace Domain\Conversation\Listeners;

use Domain\Analytics\Data\IpLocationData;
use Domain\Analytics\Services\IP2Country;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Session;
use Illuminate\Contracts\Queue\ShouldQueue;
use Support\Localization\Country\Models\Country;

class LocateSession implements ShouldQueue
{
    public function handle(SessionLocationUnknown $event): void
    {
        $session = $event->session->fresh();
        if (! $session || $session->country_id) {
            return;
        }

        // IP geo is best-effort enrichment for city/region/timezone. Behind
        // Cloudflare the stored IP is the edge PoP, so its country is unreliable
        // (Middle-East traffic geo-locates to e.g. Bulgaria) — we only consult it
        // for the finer fields, and only when it agrees with the trusted country.
        $location = $session->ip_address ? IP2Country::getLocationByIP($session->ip_address) : null;

        // Prefer the request-time country (Cloudflare's CF-IPCountry, captured
        // when the request still existed); fall back to IP geo for dev/no-CF.
        $countryCode = $event->countryCode ?: $location?->countryCode;
        if (! $countryCode) {
            return;
        }

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
        if ($location && $location->isProxy !== null) {
            $updates['is_proxy'] = $location->isProxy;
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }
}
