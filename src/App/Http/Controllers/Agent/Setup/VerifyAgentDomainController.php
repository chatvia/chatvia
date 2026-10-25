<?php

namespace App\Http\Controllers\Agent\Setup;

use Domain\Agent\Actions\Settings\VerifyAgentDomainAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Checks the TXT record of an agent's pending custom domain and, when it
 * holds the agent's token, turns the domain on. Gated by
 * `agent.permission:agent.setup`.
 */
class VerifyAgentDomainController
{
    public function __invoke(Agent $agent, VerifyAgentDomainAction $verify): RedirectResponse
    {
        $verify->execute($agent);

        return back()->with('success', __('Custom domain verified.'));
    }
}
