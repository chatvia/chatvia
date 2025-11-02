<?php

namespace App\Services;

class HostConfig
{
    public static function get(string $host)
    {
        $hosts = config('hosts');

        if (isset($hosts[$host]['alias'])) {
            $host = $hosts[$host]['alias'];
        }

        return $hosts[$host];
    }

    public static function landing(string $host)
    {
        return static::get($host)['landing'];
    }
}
