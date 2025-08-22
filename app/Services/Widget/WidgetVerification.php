<?php

namespace App\Services\Widget;

use App\Models\Agent;
use Illuminate\Http\Request;

class WidgetVerification
{
    public function verifyByAgentExternalId(string $externalId, Request $request): bool
    {
        /** @var Agent $agent */
        $agent = Agent::where('external_id', $externalId)->first();

        if (! $agent) {
            return false;
        }

        // Get the requesting domain from multiple sources
        $requestingDomain = $this->getRequestingDomain($request);

        if (! $requestingDomain) {
            return false;
        }

        // Check if domain is in the agent's allowed sites
        $widgetSettings = $agent->settings('widget');

        $allowedSites = array_merge($widgetSettings['sites'], ['localhost', 'chatvia.ai', 'chatvia.test']) ?? [];

        return $this->isDomainAllowed($requestingDomain, $allowedSites);
    }

    public function getRequestingDomain(Request $request): ?string
    {
        $origin = $request->header('origin');

        if ($origin) {
            $parsed = parse_url($origin);

            return $parsed['host'] ?? null;
        }

        $referer = $request->header('referer');
        if ($referer) {
            $parsed = parse_url($referer);

            return $parsed['host'] ?? null;
        }

        return $request->getHost();
    }

    public function isDomainAllowed(string $domain, array $allowedSites): bool
    {
        // Exact match
        if (in_array($domain, $allowedSites, true)) {
            return true;
        }

        // Check for wildcard subdomains (e.g., *.example.com)
        foreach ($allowedSites as $allowedSite) {
            if (str_starts_with($allowedSite, '*.')) {
                $baseDomain = substr($allowedSite, 2);
                if (str_ends_with($domain, '.'.$baseDomain) || $domain === $baseDomain) {
                    return true;
                }
            }
        }

        return false;
    }
}
