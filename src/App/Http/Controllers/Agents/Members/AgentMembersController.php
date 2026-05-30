<?php

namespace App\Http\Controllers\Agents\Members;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\InviteAgentMemberRequest;
use Domain\Agents\Actions\DetachChatterFromAgentAction;
use Domain\Agents\Actions\GrantChatterAccessToAgentAction;
use Domain\Agents\Actions\InviteAgentMemberAction;
use Domain\Agents\Data\Resources\AgentChatterRowData;
use Domain\Agents\Exceptions\ChatterInvitationRejectedException;
use Domain\Agents\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

/**
 * Agent-scoped Members tab. We surface every chatter in the workspace — not
 * just the ones already granted to this agent — so the owner can give an
 * existing member access to another agent in one click. Rows already on the
 * pivot sort first; the rest get a "Grant" affordance.
 *
 *   index    — list all workspace chatters with a `has_access` flag.
 *   store    — invite a brand-new chatter (creates the user + grants this agent).
 *   grant    — give an existing chatter access to this agent.
 *   destroy  — revoke this agent's grant (user stays).
 */
class AgentMembersController extends Controller
{
    /**
     * @return Response|array{data: DataCollection<int, AgentChatterRowData>}
     */
    public function index(Request $request, Agent $agent): Response|array
    {
        abort_unless(has_permission_to_agent($agent, 'agent.members.manage'), 404);

        if ($request->wantsJson()) {
            $workspace = $agent->user;

            // All chatters owned by the workspace, alphabetised. We don't
            // track when the pivot row was attached — the convention in this
            // project is no timestamps on pivot tables — so the rows just
            // carry a boolean `has_access` for this agent.
            $chatters = $workspace->chatters()->orderBy('name')->get();
            $grantedIds = $agent->chatters()->pluck('users.id')->all();
            $grantedSet = array_flip($grantedIds);

            $rows = $chatters
                ->map(fn ($chatter) => AgentChatterRowData::fromModelAndGrant(
                    $chatter,
                    isset($grantedSet[$chatter->id]),
                ))
                ->sortBy(fn ($row) => [$row->has_access ? 0 : 1, strtolower($row->name)])
                ->values();

            return ['data' => AgentChatterRowData::collect($rows, DataCollection::class)];
        }

        return Inertia::render('agents/Members', [
            'agent' => $agent,
        ]);
    }

    public function store(
        InviteAgentMemberRequest $request,
        Agent $agent,
        InviteAgentMemberAction $invite,
    ): RedirectResponse {
        abort_unless(has_permission_to_agent($agent, 'agent.members.manage'), 404);

        // Public agents have no concept of members — anyone with the URL can
        // chat. Refuse the invite so the workspace doesn't pile up unused
        // chatter rows.
        if ($agent->is_public) {
            return back()->withErrors(['email' => 'This agent is public — anyone with the link can chat with it. No invitation is needed.']);
        }

        try {
            $invitation = $invite->execute($agent, $request->email());
        } catch (ChatterInvitationRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('invitation', [
            'email' => $invitation->email,
            'accept_url' => $invitation->accept_url,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    public function grant(
        Request $request,
        Agent $agent,
        string $chatter,
        GrantChatterAccessToAgentAction $grant,
    ): RedirectResponse {
        abort_unless(has_permission_to_agent($agent, 'agent.members.manage'), 404);

        abort_if($agent->is_public, 422, 'Public agents do not use per-member grants.');

        $member = $agent->user
            ->chatters()
            ->where('external_id', $chatter)
            ->firstOrFail();

        $grant->execute($agent, $member);

        return back();
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $chatter,
        DetachChatterFromAgentAction $detach,
    ): RedirectResponse {
        abort_unless(has_permission_to_agent($agent, 'agent.members.manage'), 404);

        $member = $agent->chatters()->where('users.external_id', $chatter)->firstOrFail();

        $detach->execute($agent, $member);

        return back();
    }
}
