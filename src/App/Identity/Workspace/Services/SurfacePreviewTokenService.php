<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Mints the `data-auth` token the Playground previews (Widget, Command Bar)
 * pass to the real embed script for a protected agent — the same JWT a
 * customer mints on their backend, signed with the agent's own `auth_secret`
 * and scoped to the dashboard user, so the secret never reaches the browser.
 *
 * The token is cached for an hour less than its lifetime, keyed by (surface,
 * agent, user, secret fingerprint): reloads reuse it, and regenerating the
 * secret starts a new key. `ctx: playground` is the trust signal the widget's
 * gated endpoints read instead of the spoofable Referer header. Public agents
 * get no token.
 */
class SurfacePreviewTokenService
{
    public function __construct(
        private readonly AgentSignInTokenService $signInTokens,
    ) {}

    public function issue(string $surface, Agent $agent, User $user): ?string
    {
        if ($agent->is_public) {
            return null;
        }

        $ttlSeconds = AgentSignInTokenService::MAX_TTL_SECONDS;
        $fingerprint = Str::substr(hash('sha256', $agent->ensureAuthSecret()), 0, 12);

        return Cache::store('redis')->remember(
            "{$surface}-preview-token:agent:{$agent->id}:secret:{$fingerprint}:user:{$user->id}",
            $ttlSeconds - 3600,
            fn (): string => $this->signInTokens->issue(
                $user,
                $agent,
                ttlSeconds: $ttlSeconds,
                ctx: AgentSignInTokenService::CTX_PLAYGROUND,
            )['token'],
        );
    }
}
