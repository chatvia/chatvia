<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;

/**
 * Builds an agent's public URLs. Every agent's Hosted Chat lives on its own
 * subdomain of the hosting base URL (`chatvia.agent.hosting_url`, falling back
 * to APP_URL): the friendly one under its renameable `sub_domain`, and a
 * permanent one under its `external_id` that never changes (embed snippets use
 * that one). The base URL's scheme and port are kept, so a local install on
 * `http://localhost:8000` links to `http://{sub}.localhost:8000`.
 */
final class AgentUrlBuilder
{
    /** `{scheme}://{sub_domain}.{host}` — changes when the agent is renamed. */
    public static function hostedUrl(Agent $agent): string
    {
        return self::onSubdomain((string) $agent->sub_domain);
    }

    /** `{scheme}://{external_id}.{host}` — stable for the agent's lifetime. */
    public static function permanentUrl(Agent $agent): string
    {
        return self::onSubdomain($agent->external_id);
    }

    /** The Hosted Chat page. */
    public static function chatUrl(Agent $agent): string
    {
        return self::hostedUrl($agent).'/chat';
    }

    /** The chat page the embed script frames. */
    public static function chatEmbedUrl(Agent $agent): string
    {
        return self::permanentUrl($agent).'/chat/embed';
    }

    private static function onSubdomain(string $label): string
    {
        $base = parse_url((string) (config('chatvia.agent.hosting_url') ?: config('app.url')));

        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? 'localhost';
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        return "{$scheme}://{$label}.{$host}{$port}";
    }
}
