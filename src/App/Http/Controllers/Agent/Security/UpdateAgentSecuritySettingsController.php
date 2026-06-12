<?php

namespace App\Http\Controllers\Agent\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\Security\SaveAgentSecuritySettingsRequest;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Persists the per-agent chat rate-limit settings (the `security` group) from
 * the agent's Security page. Mirrors the guardrails save flow — a single thin
 * write that loops the validated DTO into Agent::setSetting.
 */
class UpdateAgentSecuritySettingsController extends Controller
{
    public function __invoke(SaveAgentSecuritySettingsRequest $request, Agent $agent): RedirectResponse
    {
        abort_unless(has_permission_to_agent($agent, 'agent.identity.manage'), 404);

        foreach ($request->toData()->toSettings() as $key => $value) {
            $agent->setSetting(key: $key, value: $value, group: 'security');
        }

        return back();
    }
}
