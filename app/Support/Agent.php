<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

    public static function deleteAgent(string $id)
    {
        Http::delete(config('services.ingestion.api_endpoint').'/agent', [
            'id' => $id,
        ]);
    }
}
