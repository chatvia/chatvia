<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;

/**
 * Builds an agent's public URLs. Every agent's Hosted Chat lives under
 * `{APP_URL}/chat/`: the friendly address under its renameable `slug`, and a
 * permanent one under its `external_id` that never changes (embed snippets use
 * that one). Built from APP_URL, so a link is the same whichever host (or
 * queue worker) builds it.
 *
 * An agent with a verified custom domain ({@see CustomDomainLookup}) is
 * shared as `https://{domain}` instead, and its chat pages live under that
 * domain's `/chat/` path.
 */
final class AgentUrlBuilder
{
    /** `{APP_URL}/chat` — the base of every agent's Hosted Chat. */
    public static function chatBaseUrl(): string
    {
        return Str::rtrim((string) config('app.url'), '/').'/chat';
    }

    /** `https://{custom domain}`, or APP_URL for an agent without one. */
    public static function origin(Agent $agent): string
    {
        $host = self::activeHost($agent);

        return $host === null ? Str::rtrim((string) config('app.url'), '/') : "https://{$host}";
    }

    /** `{base}/{slug}` — changes when the agent is renamed; the custom domain itself when it has one. */
    public static function hostedUrl(Agent $agent): string
    {
        return self::customOrigin($agent) ?? self::chatBaseUrl().'/'.rawurlencode($agent->slug ?? $agent->external_id);
    }

    /** `{base}/{external_id}` — stable for the agent's lifetime; the custom domain itself when it has one. */
    public static function permanentUrl(Agent $agent): string
    {
        return self::customOrigin($agent) ?? self::chatBaseUrl().'/'.rawurlencode($agent->external_id);
    }

    /** The Hosted Chat page, on the agent's custom domain when it has one. */
    public static function chatUrl(Agent $agent): string
    {
        return self::origin($agent).'/chat/'.rawurlencode($agent->slug ?? $agent->external_id);
    }

    /** The chat page the embed script frames. */
    public static function chatEmbedUrl(Agent $agent): string
    {
        return self::origin($agent).'/chat/'.rawurlencode($agent->external_id).'/embed';
    }

    private static function customOrigin(Agent $agent): ?string
    {
        $host = self::activeHost($agent);

        return $host === null ? null : "https://{$host}";
    }

    /** An agent that is not saved yet has no domain. */
    private static function activeHost(Agent $agent): ?string
    {
        return $agent->exists ? app(CustomDomainLookup::class)->activeHostFor($agent->id) : null;
    }
}
