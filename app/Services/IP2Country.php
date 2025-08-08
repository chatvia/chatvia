<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IP2Country
{
    public static function getCountryByIP($ip): ?string
    {
        $result = Http::get('http://ip-api.com/json/'.$ip)->throw()->json();

        if ($result['status'] !== 'success') {
            return null;
        }

        return strtolower($result['countryCode']);
    }
}
