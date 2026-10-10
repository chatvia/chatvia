<?php

namespace App\Http\Controllers\Settings\Chatter;

use App\Http\Controllers\Settings\Chatter\Concerns\ResolvesWorkspaceChatters;
use App\Http\Requests\Settings\Chatter\InviteWorkspaceChatterRequest;
use App\Http\Requests\Settings\Chatter\UpdateChatterRequest;
use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Chatter\Actions\UpdateChatterAccessAction;
use App\Identity\Chatter\Data\WorkspaceChatterRowData;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\InviteWorkspaceChatterAction;
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
 * All endpoints are gated by `account.chatters.manage`;
 * {@see ResolvesWorkspaceChatters} enforces the workspace boundary so an
 * employee can never reach a chatter from a different owner.
 */
class SettingsChatterController
{
    use ResolvesWorkspaceChatters;

    /**
     * @return Response|array{data: DataCollection<int, WorkspaceChatterRowData>}
     */
    public function index(Request $request): Response|array
    {
        if ($request->wantsJson()) {
            $chatters = $request->user()->workspace()
                ->chatters()
                ->withCount('accessibleAgents')
                ->with('messageLimit')
                ->orderBy('name')
                ->get();

            return ['data' => WorkspaceChatterRowData::collect($chatters, DataCollection::class)];
        }

        // Protected agents the owner can invite a new member into — public
        // agents have no per-member access, so they're never selectable.
        $agents = $request->user()->workspace()
            ->agents()
            ->where('is_public', false)
            ->orderBy('agents.name')
            ->get(['agents.external_id', 'agents.name'])
            ->map(fn (Agent $agent): array => ['external_id' => $agent->external_id, 'name' => $agent->name])
            ->all();

        return Inertia::render('workspace/people/Chatters', [
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

        return back()->with('invitation', [
            'email' => $invitation->email,
            'accept_url' => route('agents.invitation.show', ['token' => $invitation->token]),
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Update a member's access controls from the detail sheet: the optional
     * monthly message limit and the deactivation switch + reason. Returns the
     * refreshed row so the sheet can merge it without a full refetch.
     */
    public function update(
        UpdateChatterRequest $request,
        string $chatter,
        UpdateChatterAccessAction $update,
    ): WorkspaceChatterRowData {
        $member = $this->findChatter($request, $chatter);

        $update->execute($request->toData($member));

        return WorkspaceChatterRowData::fromModel(
            $member->loadCount('accessibleAgents')->load('messageLimit'),
        );
    }

    public function destroy(
        Request $request,
        string $chatter,
        DeleteChatterAction $delete,
    ): RedirectResponse {
        $member = $this->findChatter($request, $chatter);

        $delete->execute($member);

        return back()->with('success', __(':name removed.', ['name' => $member->name]));
    }
}
