<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IP2Country
{
    public static function getCountryByIP($ip): string
    {
        // Validate IP address format
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        // Skip geolocation for private/local IP addresses
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return '';
        }

        $url = Str::of(rtrim(config('services.ipapi.endpoint'), '/'))
            ->append($ip)
            ->append('?access_key='.config('services.ipapi.key'))
            ->toString();

        $result = Http::get($url)
            ->throw()
            ->json();

        return strtolower($result['country_code'] ?? '');
    }
}
