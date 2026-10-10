<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;

/**
 * Builds an agent's public URLs. Every agent's Hosted Chat lives under
 * `{APP_URL}/chat/`: the friendly address under its renameable `slug`, and a
 * permanent one under its `external_id` that never changes (embed snippets use
 * that one). Built from APP_URL, so a link is the same whichever host (or
 * queue worker) builds it.
 */
final class AgentUrlBuilder
{
    /** `{APP_URL}/chat` — the base of every agent's Hosted Chat. */
    public static function chatBaseUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/chat';
    }

    /** `{base}/{slug}` — changes when the agent is renamed. */
    public static function hostedUrl(Agent $agent): string
    {
        return self::chatBaseUrl().'/'.rawurlencode($agent->slug ?? $agent->external_id);
    }

    /** `{base}/{external_id}` — stable for the agent's lifetime. */
    public static function permanentUrl(Agent $agent): string
    {
        return self::chatBaseUrl().'/'.rawurlencode($agent->external_id);
    }

    /** The Hosted Chat page. */
    public static function chatUrl(Agent $agent): string
    {
        return self::hostedUrl($agent);
    }

    /** The chat page the embed script frames. */
    public static function chatEmbedUrl(Agent $agent): string
    {
        return self::permanentUrl($agent).'/embed';
    }
}
