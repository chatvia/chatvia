<?php

namespace App\Http\Controllers\Agent\Chatter;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Illuminate\Http\RedirectResponse;

/**
 * Gives an existing workspace chatter access to this agent (the "Grant"
 * button on the agent's Chatters tab). Public agents have no per-chatter
 * access, so they refuse the grant.
 */
class GrantAgentChatterAccessController
{
    public function __invoke(Agent $agent, string $chatter, GrantAgentMembershipAction $grant): RedirectResponse
    {
        abort_if($agent->is_public, 422, __('Public agents do not use per-member grants.'));

        $member = $agent->workspace
            ->chatters()
            ->where('external_id', $chatter)
            ->firstOrFail();

        $grant->execute($agent, $member);

        return back();
    }
}
