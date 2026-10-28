<?php

namespace App\Http\Controllers\Agent\Chatter;

use App\Http\Requests\Agent\Chatter\InviteAgentChatterRequest;
use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteAgentChatterAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;
use Domain\Membership\Agent\Data\Resources\AgentChatterRowData;
use Domain\Membership\Agent\Data\Resources\ChatterInvitationLinkData;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Queries\AgentChatterRowsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

/**
 * Agent-scoped Members tab. We surface every chatter in the workspace — not
 * just the ones already granted to this agent — so the owner can give an
 * existing member access to another agent in one click. Rows already on the
 * pivot sort first; the rest get a "Grant" affordance
 * ({@see GrantAgentChatterAccessController}).
 *
 *   index    — list all workspace chatters with a `has_access` flag.
 *   store    — invite a brand-new chatter (creates the user + grants this agent).
 *   destroy  — revoke this agent's grant (user stays).
 */
class AgentChatterController
{
    /**
     * @return Response|array{data: DataCollection<int, AgentChatterRowData>}
     */
    public function index(Request $request, Agent $agent, AgentChatterRowsQuery $rows): Response|array
    {
        if ($request->wantsJson()) {
            return ['data' => $rows->forAgent($agent)];
        }

        return Inertia::render('dashboard/agents/pages/Chatters', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function store(
        InviteAgentChatterRequest $request,
        Agent $agent,
        InviteAgentChatterAction $invite,
    ): RedirectResponse {
        try {
            $invitation = $invite->execute($request->toData($agent));
        } catch (AgentChatterInvitationRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('invitation', ChatterInvitationLinkData::fromInvitation($invitation)->toArray());
    }

    public function destroy(
        Agent $agent,
        Chatter $chatter,
        RevokeAgentMembershipAction $revoke,
    ): RedirectResponse {
        $revoke->execute($agent, $chatter);

        return back();
    }
}
