<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Http\Controllers\Settings\Chatter\Concerns\ResolvesWorkspaceChatters;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;
use Illuminate\Http\Request;

/**
 * The Agents tab of a workspace chatter's detail sheet (Settings → Members):
 * list the agents the chatter can access, grant one, revoke one. Every
 * response is the full payload — every protected agent in the workspace,
 * partitioned into `attached` (already granted) and `available` (toggle on to
 * grant) — matching the shape consumed by
 * {@see resources/js/composables/useAgentAttachments.ts}.
 *
 * Gated by `account.chatters.manage`; {@see ResolvesWorkspaceChatters} keeps
 * both the chatter and the agent inside the workspace.
 */
class SettingsChatterAgentController
{
    use ResolvesWorkspaceChatters;

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function index(Request $request, string $chatter): array
    {
        $member = $this->findChatter($request, $chatter);

        return $this->agentsPayload($request, $member);
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function store(Request $request, string $chatter, string $agent, GrantAgentMembershipAction $grant): array
    {
        $member = $this->findChatter($request, $chatter);

        $grant->execute($this->findWorkspaceAgent($request, $agent), $member);

        return $this->agentsPayload($request, $member);
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function destroy(Request $request, string $chatter, string $agent, RevokeAgentMembershipAction $revoke): array
    {
        $member = $this->findChatter($request, $chatter);

        $revoke->execute($this->findWorkspaceAgent($request, $agent), $member);

        return $this->agentsPayload($request, $member);
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    private function agentsPayload(Request $request, User $member): array
    {
        // Only protected agents can have members — public agents are open
        // to anyone so per-chatter grants are meaningless.
        $workspaceAgents = $request->user()->workspace()
            ->agents()
            ->where('is_public', false)
            ->orderBy('agents.name')
            ->get(['agents.id', 'agents.external_id', 'agents.name']);

        $attachedIds = array_flip($member->accessibleAgents()->pluck('agents.id')->all());

        $attached = [];
        $available = [];

        // The relation returns Collection<int, Model>; the rows are Agent
        // instances by construction, but the static type erases that.
        /** @var Agent $agent */
        foreach ($workspaceAgents as $agent) {
            $row = ['id' => $agent->external_id, 'name' => $agent->name];
            if (isset($attachedIds[$agent->id])) {
                $attached[] = $row;
            } else {
                $available[] = $row;
            }
        }

        return ['attached' => $attached, 'available' => $available];
    }
}
