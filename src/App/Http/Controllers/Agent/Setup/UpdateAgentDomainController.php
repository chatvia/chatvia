<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\UpdateAgentDomainRequest;
use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Sets or removes the custom domain of an agent's Hosted Chat. Gated by
 * `agent.permission:agent.setup`.
 */
class UpdateAgentDomainController
{
    public function __invoke(
        UpdateAgentDomainRequest $request,
        Agent $agent,
        SetAgentDomainAction $setDomain,
    ): RedirectResponse {
        $newValue = $request->toData()->domain;

        $setDomain->execute($agent, $newValue);

        return back()->with('success', $newValue ? __('Custom domain updated.') : __('Custom domain removed.'));
    }
}
