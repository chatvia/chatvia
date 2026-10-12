<?php

namespace App\Http\Controllers\Agent\Chatter;

use App\Http\Requests\Agent\Chatter\InviteAgentChatterRequest;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteAgentChatterAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;
use Domain\Membership\Agent\Data\Resources\AgentChatterRowData;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
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
    public function index(Request $request, Agent $agent): Response|array
    {
        if ($request->wantsJson()) {
            $agent->loadMissing('workspace');
            $workspace = $agent->workspace;

            // All chatters owned by the workspace, alphabetised. We don't
            // track when the pivot row was attached — the convention in this
            // project is no timestamps on pivot tables — so the rows just
            // carry a boolean `has_access` for this agent.
            $chatters = $workspace->chatters()->orderBy('name')->get();
            $grantedIds = $agent->chatters()->pluck('chatters.id')->all();
            $grantedSet = array_flip($grantedIds);

            // Outstanding invitations for this agent, keyed by lower-cased
            // email. A pending member's sign-up link lives here; we surface it
            // so the owner can copy it from the member sheet.
            $invitations = AgentChatterInvitation::query()
                ->where('agent_id', $agent->id)
                ->get()
                ->keyBy(fn (AgentChatterInvitation $invitation): string => strtolower($invitation->email));

            $rows = $chatters
                ->map(function ($chatter) use ($grantedSet, $invitations): AgentChatterRowData {
                    $invitationUrl = null;
                    $invitationExpiresAt = null;

                    if ($chatter->email_verified_at === null && $chatter->email !== null) {
                        $invitation = $invitations->get(strtolower($chatter->email));

                        if ($invitation !== null && ! $invitation->hasExpired()) {
                            $invitationUrl = route('agents.invitation.show', ['token' => $invitation->token]);
                            $invitationExpiresAt = $invitation->expires_at->toIso8601String();
                        }
                    }

                    return AgentChatterRowData::fromModelAndGrant(
                        $chatter,
                        isset($grantedSet[$chatter->id]),
                        $invitationUrl,
                        $invitationExpiresAt,
                    );
                })
                ->sortBy(fn ($row): array => [$row->has_access ? 0 : 1, strtolower($row->name)])
                ->values();

            return ['data' => AgentChatterRowData::collect($rows, DataCollection::class)];
        }

        return Inertia::render('agents/Chatters', [
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

        return back()->with('invitation', [
            'email' => $invitation->email,
            'accept_url' => route('agents.invitation.show', ['token' => $invitation->token]),
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    public function destroy(
        Request $request,
        Agent $agent,
        string $chatter,
        RevokeAgentMembershipAction $revoke,
    ): RedirectResponse {
        $member = $agent->chatters()->where('chatters.external_id', $chatter)->firstOrFail();

        $revoke->execute($agent, $member);

        return back();
    }
}
