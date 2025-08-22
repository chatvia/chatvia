<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Agent
{
    public static function exists($name): bool
    {
        return Cache::has('agent-'.$name);
    }

    public static function missing($name): bool
    {
        return self::exists($name) === false;
    }

    public static function cacheAgentName($name): void
    {
        Cache::forever('agent-'.$name, '');
    }

    public static function resolveAgentFromSubdomain($subdomain): ?\App\Models\Agent
    {
        return \App\Models\Agent::query()
            ->where('sub_domain', $subdomain)
            ->orWhere('external_id', $subdomain)
            ->first();
    }

    public static function generateRandomExternalId(int $len = 14): string
    {
        do {
            $externalId = substr(bin2hex(random_bytes((int) ceil($len / 2))), 0, $len);
        } while (\App\Models\Agent::where('external_id', $externalId)->exists());

        return $externalId;
    }
}
