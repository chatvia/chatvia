<?php

namespace App\Http\Controllers\Agent\Security;

use App\Http\Requests\Agent\Security\UpdateAgentVisibilityRequest;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;

/**
 * Makes an agent public or protected. A single column write, so it stays in
 * the controller. Messaging channels keep refusing a protected agent at run
 * time (see ChannelAccessGate).
 */
class AgentSecurityUpdateController
{
    public function __invoke(UpdateAgentVisibilityRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update(['is_public' => $request->isPublic()]);

        return back()->with('success', __('Security settings updated successfully'));
    }
}
