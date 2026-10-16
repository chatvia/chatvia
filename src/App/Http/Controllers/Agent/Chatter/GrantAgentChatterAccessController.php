<?php

namespace App\Http\Controllers\Agent\Chatter;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Illuminate\Http\RedirectResponse;

/**
 * Gives an existing workspace chatter access to this agent (the "Grant"
 * button on the agent's Chatters tab). Public agents have no per-chatter
 * access, so they refuse the grant. The bound `{chatter}` is 404-checked
 * against the agent's workspace by `can.access.agent`.
 */
class GrantAgentChatterAccessController
{
    public function __invoke(Agent $agent, Chatter $chatter, GrantAgentMembershipAction $grant): RedirectResponse
    {
        abort_if($agent->is_public, 422, __('Public agents do not use per-member grants.'));

        $grant->execute($agent, $chatter);

        return back();
    }
}
