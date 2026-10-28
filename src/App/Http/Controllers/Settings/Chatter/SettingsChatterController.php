<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Http\Requests\Settings\Chatter\InviteWorkspaceChatterRequest;
use App\Http\Requests\Settings\Chatter\UpdateChatterRequest;
use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\WorkspaceChatterRowData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteWorkspaceChatterAction;
use Domain\Membership\Agent\Data\Resources\ChatterInvitationLinkData;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;

/**
 * Workspace-level Members management (Settings → Members). Lists every
 * chatter (`type=chatter`) under the logged-in owner's workspace, invites a
 * new one, updates a chatter's access controls or removes the chatter
 * entirely. Per-agent access is {@see SettingsChatterAgentController}; the
 * password-reset email is {@see ResetChatterPasswordController}.
 *
 * All endpoints are gated by `account.chatters.manage`; `workspace.scope`
 * 404s a bound chatter of another workspace, so an employee can never reach
 * a chatter from a different owner.
 */
class SettingsChatterController
{
    /**
     * @return Response|array{data: DataCollection<int, WorkspaceChatterRowData>}
     */
    public function index(Request $request, AgentAccessPolicy $access): Response|array
    {
        if ($request->wantsJson()) {
            $chatters = $request->user()->workspace()
                ->chatters()
                ->withCount('agents')
                ->with('messageLimit')
                ->orderBy('name')
                ->get();

            return ['data' => WorkspaceChatterRowData::collect($chatters, DataCollection::class)];
        }

        // Protected agents the viewer may invite a new member into: public
        // agents have no per-member access, and a teammate only hands out the
        // agents their team role reaches.
        $agents = $access->manageableAgents($request->user())
            ->reject(fn (Agent $agent): bool => $agent->is_public)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Agent $agent): array => ['external_id' => $agent->external_id, 'name' => $agent->name])
            ->values()
            ->all();

        return Inertia::render('dashboard/workspace/pages/people/Chatters', [
            'agents' => $agents,
        ]);
    }

    /**
     * Invite a chatter directly from the workspace Members page, granting them
     * access to one or more protected agents in the same step. Returns the
     * invitation so the UI can show (and copy) the sign-up link.
     */
    public function store(
        InviteWorkspaceChatterRequest $request,
        InviteWorkspaceChatterAction $invite,
    ): RedirectResponse {
        try {
            $invitation = $invite->execute($request->toData());
        } catch (AgentChatterInvitationRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        return back()->with('invitation', ChatterInvitationLinkData::fromInvitation($invitation)->toArray());
    }

    /**
     * Update a member's access controls from the detail sheet: the optional
     * monthly message limit and the deactivation switch + reason. Returns the
     * refreshed row so the sheet can merge it without a full refetch.
     */
    public function update(
        UpdateChatterRequest $request,
        Chatter $chatter,
        UpdateChatterAccessAction $update,
    ): WorkspaceChatterRowData {
        $update->execute($request->toData($chatter));

        return WorkspaceChatterRowData::fromModel(
            $chatter->loadCount('agents')->load('messageLimit'),
        );
    }

    public function destroy(
        Chatter $chatter,
        DeleteChatterAction $delete,
    ): RedirectResponse {
        $delete->execute($chatter);

        return back()->with('success', __(':name removed.', ['name' => $chatter->name]));
    }
}
