<?php

namespace App\Http\Controllers\Agent\BusinessProfile;

use App\Http\Requests\Agent\Settings\UpdateBusinessProfileRequest;
use Domain\Agent\Actions\Settings\DeleteAgentBusinessProfileAction;
use Domain\Agent\Actions\Settings\UpdateAgentBusinessProfileAction;
use Domain\Agent\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AgentBusinessProfileController
{
    public function update(
        UpdateBusinessProfileRequest $request,
        Agent $agent,
        UpdateAgentBusinessProfileAction $update,
    ): RedirectResponse {
        abort_unless(has_permission_to_agent($agent, 'agent.business_profile.manage'), 404);

        $update->execute($agent, $request->toData());

        return back()->with('success', 'Business profile saved.');
    }

    public function destroy(
        Request $request,
        Agent $agent,
        DeleteAgentBusinessProfileAction $delete,
    ): RedirectResponse {
        abort_unless(has_permission_to_agent($agent, 'agent.business_profile.manage'), 404);

        $delete->execute($agent);

        return back()->with('success', 'Business profile deleted.');
    }
}
