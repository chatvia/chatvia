<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\ListChatterAgentAssignmentAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;
use Domain\Membership\Agent\Data\Resources\AgentMembershipAssignmentData;
use Illuminate\Http\Request;

/**
 * The Agents tab of a workspace chatter's detail sheet (Settings → Members):
 * list the agents the chatter can access, grant one, revoke one. Every
 * response is the full payload — every protected agent in the workspace the
 * viewer may manage, partitioned into `attached` (already granted) and
 * `available` (toggle on to grant) — matching the shape consumed by
 * {@see resources/js/composables/useAgentAttachments.ts}.
 *
 * Gated by `account.chatters.manage`; `workspace.scope` 404s a bound chatter
 * or agent of another workspace, and `can.access.agent` on grant and revoke
 * refuses an agent outside the viewer's team role.
 */
class SettingsChatterAgentController
{
    public function index(Request $request, Chatter $chatter, ListChatterAgentAssignmentAction $list): AgentMembershipAssignmentData
    {
        return $list->execute($chatter, $request->user());
    }

    public function store(
        Request $request,
        Chatter $chatter,
        Agent $agent,
        GrantAgentMembershipAction $grant,
        ListChatterAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        $grant->execute($agent, $chatter);

        return $list->execute($chatter, $request->user());
    }

    public function destroy(
        Request $request,
        Chatter $chatter,
        Agent $agent,
        RevokeAgentMembershipAction $revoke,
        ListChatterAgentAssignmentAction $list,
    ): AgentMembershipAssignmentData {
        $revoke->execute($agent, $chatter);

        return $list->execute($chatter, $request->user());
    }
}
