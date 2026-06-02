<?php

namespace App\Http\Controllers\Agent\Bar;

use App\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Render the iframe that powers the Bar surface preview in the Playground.
 *
 * The bar is a cmd-K style launcher embedded into a host page; to make the
 * preview faithful to what a customer would see, the view ships a sample
 * landing-page backdrop and mounts the bar on top of it. Auth handling is
 * identical to the widget preview — a server-minted JWT scoped to the
 * dashboard user, cached for ~23h keyed by (agent, user, secret-fingerprint)
 * so reloads reuse the same token and a regenerated `auth_secret` invalidates
 * the cache automatically.
 *
 * Public agents skip the token entirely; the embed script loads as a stranger
 * would see it.
 */
class BarPreviewController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        AgentSignInTokenService $signInTokens,
    ): Factory|View {
        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $ttlSeconds = AgentSignInTokenService::MAX_TTL_SECONDS;

            $fingerprint = Str::substr(hash('sha256', $agent->ensureAuthSecret()), 0, 12);
            $cacheKey = "bar-preview-token:agent:{$agent->id}:secret:{$fingerprint}:user:{$user->id}";

            $cacheTtl = $ttlSeconds - 3600;

            $authToken = Cache::store('redis')->remember(
                $cacheKey,
                $cacheTtl,
                fn (): string => $signInTokens->issue(
                    $user,
                    $agent,
                    ttlSeconds: $ttlSeconds,
                    ctx: AgentSignInTokenService::CTX_PLAYGROUND,
                )['token'],
            );
        }

        $theme = $request->query('theme');
        if (! in_array($theme, ['auto', 'light', 'dark'], true)) {
            $theme = null;
        }

        return view('bar.preview', [
            'embedScriptUrl' => config('widget.script_url_embed'),
            'agentId' => $agent->external_id,
            'agentName' => $agent->name,
            'authToken' => $authToken,
            'language' => $request->query('language', 'auto'),
            'theme' => $theme,
        ]);
    }
}
