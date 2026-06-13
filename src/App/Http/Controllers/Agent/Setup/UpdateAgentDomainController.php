<?php

namespace App\Http\Controllers\Agent\Setup;

use App\Http\Requests\Agent\UpdateAgentDomainRequest;
use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

class UpdateAgentDomainController
{
    public function __invoke(
        UpdateAgentDomainRequest $request,
        Agent $agent,
        SetAgentDomainAction $setDomain,
    ): RedirectResponse {
        abort_if($request->user()->cannot('update', $agent), 404);

        $newValue = $request->toData()->domain;

        // The quota gate lives in the Action. A DomainValidationException it
        // throws bubbles to the global handler, which redirects back with the
        // `domain` field error — no inline rule or duplicated count here.
        $setDomain->execute($agent, $request->user(), $newValue);

        return back()->with('success', $newValue ? 'Custom domain updated.' : 'Custom domain removed.');
    }
}
