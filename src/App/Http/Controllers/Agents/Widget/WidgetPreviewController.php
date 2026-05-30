<?php

namespace App\Http\Controllers\Agents\Widget;

use App\Auth\Services\AgentSignInTokenService;
use Domain\Agents\Models\Agent;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WidgetPreviewController
{
    /**
     * Render the iframe that powers the Live Preview on the Setup tab. The
     * embed script gates a protected agent on a signed `data-auth` token —
     * the same JWT a customer would mint on their backend. For the preview
     * we mint it server-side, signed with the agent's own `auth_secret` and
     * scoped to the dashboard user looking at the page, so the preview works
     * without exposing the secret to the browser.
     *
     * The token is cached for 24h, keyed by (agent, user, secret-fingerprint),
     * so reloading the Setup tab — which the owner does constantly while
     * editing — reuses the same token instead of re-minting on every hit.
     * The fingerprint is the first 12 chars of sha256(auth_secret), so
     * regenerating the agent's secret transparently invalidates the cache:
     * the old key is orphaned (it'll expire on its own) and a new one is
     * minted under the new fingerprint.
     *
     * Public agents skip the token entirely; the embed script loads as a
     * stranger would see it.
     */
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
            $cacheKey = "widget-preview-token:agent:{$agent->id}:secret:{$fingerprint}:user:{$user->id}";

            // Cache for one hour less than the token's life so we always
            // serve a token with healthy headroom — never one about to expire.
            $cacheTtl = $ttlSeconds - 3600;

            // `ctx: playground` is the trust signal the widget's gated
            // endpoints (StreamController) read instead of the spoofable
            // Referer header. The claim survives the JWT → opaque-session
            // exchange via WidgetAuthSessionService meta.
            $authToken = Cache::store('redis')->remember(
                $cacheKey,
                $cacheTtl,
                fn () => $signInTokens->issue(
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

        return view('widget.preview', [
            'embedScriptUrl' => config('widget.script_url_embed'),
            'agentId' => $agent->external_id,
            'authToken' => $authToken,
            'language' => $request->query('language', 'auto'),
            'mode' => $request->query('mode') === 'bar' ? 'bar' : 'widget',
            'theme' => $theme,
        ]);
    }
}
