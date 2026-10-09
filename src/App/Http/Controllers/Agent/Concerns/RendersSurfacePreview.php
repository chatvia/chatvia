<?php

namespace App\Http\Controllers\Agent\Concerns;

use App\Http\Controllers\Agent\Bar\BarPreviewController;
use App\Http\Controllers\Agent\Widget\WidgetPreviewController;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Shared by the Playground previews ({@see WidgetPreviewController},
 * {@see BarPreviewController}), which load the real embed script in an iframe.
 *
 * The embed script gates a protected agent on a signed `data-auth` token — the
 * same JWT a customer mints on their backend. For the preview it is minted
 * here, signed with the agent's own `auth_secret` and scoped to the dashboard
 * user, so the secret never reaches the browser. The token is cached for an
 * hour less than its lifetime, keyed by (surface, agent, user, secret
 * fingerprint): reloads reuse it, and regenerating the secret starts a new key.
 * `ctx: playground` is the trust signal the widget's gated endpoints read
 * instead of the spoofable Referer header. Public agents get no token.
 */
trait RendersSurfacePreview
{
    private function previewToken(string $surface, Agent $agent, User $user, AgentSignInTokenService $signInTokens): ?string
    {
        if ($agent->is_public) {
            return null;
        }

        $ttlSeconds = AgentSignInTokenService::MAX_TTL_SECONDS;
        $fingerprint = Str::substr(hash('sha256', $agent->ensureAuthSecret()), 0, 12);

        return Cache::store('redis')->remember(
            "{$surface}-preview-token:agent:{$agent->id}:secret:{$fingerprint}:user:{$user->id}",
            $ttlSeconds - 3600,
            fn (): string => $signInTokens->issue(
                $user,
                $agent,
                ttlSeconds: $ttlSeconds,
                ctx: AgentSignInTokenService::CTX_PLAYGROUND,
            )['token'],
        );
    }

    /** The preview theme from `?theme=`, or null for the agent's own. */
    private function previewTheme(Request $request): ?string
    {
        $theme = $request->query('theme');

        return in_array($theme, ['auto', 'light', 'dark'], true) ? $theme : null;
    }
}
