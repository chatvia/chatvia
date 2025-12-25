<?php

namespace App\Services\Widget;

use App\Models\Agent;
use App\Models\ChatConversation;
use App\Models\ChatSession;
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

        $allowedSites = array_merge($widgetSettings['sites'], ['localhost', 'chatvia.test', 'chatvia.ai']) ?? [];

        return $this->isDomainAllowed($requestingDomain, $allowedSites);
    }

    public function verifySessionByHmacHashId(?string $sessionId): ?ChatSession
    {
        if (! $sessionId) {
            return null;
        }

        $session = ChatSession::where('external_id', $sessionId)->first();

        if (! $session) {
            return null;
        }

        return $session;
    }

    public function verifyThreadId(?string $threadId): ?ChatConversation
    {
        if (! $threadId) {
            return null;
        }

        $thread = ChatConversation::where('external_id', $threadId)->first();

        if (! $thread) {
            return null;
        }

        return $thread;
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

        // Check www variations
        $domainWithoutWww = preg_replace('/^www\./', '', $domain);
        $domainWithWww = str_starts_with($domain, 'www.') ? $domain : 'www.'.$domain;

        foreach ($allowedSites as $allowedSite) {
            // Check if allowed site matches domain without www or with www
            $allowedWithoutWww = preg_replace('/^www\./', '', $allowedSite);
            $allowedWithWww = str_starts_with($allowedSite, 'www.') ? $allowedSite : 'www.'.$allowedSite;

            if ($domainWithoutWww === $allowedWithoutWww ||
                $domain === $allowedWithWww ||
                $domainWithWww === $allowedSite) {
                return true;
            }

            // Check for wildcard subdomains (e.g., *.example.com)
            if (str_starts_with($allowedSite, '*.')) {
                $baseDomain = substr($allowedSite, 2);
                if ($domainWithoutWww === $baseDomain ||
                    str_ends_with($domainWithoutWww, '.'.$baseDomain) ||
                    str_ends_with($domain, '.'.$baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }
}
