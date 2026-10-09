<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Identity;

use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Mint a fresh `auth_secret` for this agent — invalidates every sign-in
 * link/token previously issued for it (chat `?token=` + widget `data-auth`).
 * Gated by `agent.permission:agent.identity.manage` on the route.
 */
class RegenerateAgentAuthSecretController
{
    public function __invoke(Agent $agent): RedirectResponse
    {
        $agent->regenerateAuthSecret();

        return back();
    }
}
