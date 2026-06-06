<?php

namespace Domain\Conversation\Listeners;

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
        if (! $session || ! $session->ip_address || $session->country_id) {
            return;
        }

        $location = IP2Country::getLocationByIP($session->ip_address);
        if (! $location) {
            return;
        }

        $this->stamp($session, $location);
    }

    private function stamp(Session $session, mixed $location): void
    {
        $updates = [];

        if ($location->countryCode) {
            $country = Country::query()->where('code', $location->countryCode)->first();
            if ($country) {
                $updates['country_id'] = $country->id;
            }
        }

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

        if ($location->isProxy !== null) {
            $updates['is_proxy'] = $location->isProxy;
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }
}
