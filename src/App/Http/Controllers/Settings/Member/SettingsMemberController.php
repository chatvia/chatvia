<?php

namespace App\Http\Controllers\Settings\Member;

use App\Http\Controllers\Controller;
use App\Identity\Chatter\Actions\DeleteChatterAction;
use App\Identity\Chatter\Actions\ResetChatterPasswordAction;
use App\Identity\Chatter\Data\WorkspaceChatterRowData;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\GrantAgentMembershipAction;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelData\DataCollection;
use Support\Enums\User\UserType;

/**
 * Workspace-level Members management (Settings → Members). Lists every
 * chatter (`type=chatter`) under the logged-in owner's workspace, with
 * actions to send a password-reset email, grant/revoke per-agent access,
 * or remove the chatter entirely.
 *
 * All endpoints are gated by `account.members.manage`; the resolver enforces
 * the workspace boundary so an employee can never reach a chatter from a
 * different owner, and never grants access to an agent outside the workspace.
 */
class SettingsMemberController extends Controller
{
    /**
     * @return Response|array{data: DataCollection<int, WorkspaceChatterRowData>}
     */
    public function index(Request $request): Response|array
    {
        abort_unless(has_team_permission('account.members.manage'), 404);

        if ($request->wantsJson()) {
            $chatters = $request->user()->effectiveUser()
                ->chatters()
                ->withCount('accessibleAgents')
                ->orderBy('name')
                ->get();

            return ['data' => WorkspaceChatterRowData::collect($chatters, DataCollection::class)];
        }

        return Inertia::render('settings/Members');
    }

    /**
     * The Agents tab payload for the detail sheet — every agent in the
     * workspace, partitioned into `attached` (already granted) and
     * `available` (toggle on to grant). Matches the shape consumed by
     * {@see resources/js/composables/useAgentAttachments.ts}.
     *
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function listAgents(Request $request, string $chatter): array
    {
        abort_unless(has_team_permission('account.members.manage'), 404);

        $member = $this->findChatter($request, $chatter);

        return $this->buildAgentsPayload($request, $member);
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function attachAgent(
        Request $request,
        string $chatter,
        string $agent,
        GrantAgentMembershipAction $grant,
    ): array {
        abort_unless(has_team_permission('account.members.manage'), 404);

        $member = $this->findChatter($request, $chatter);
        $agentModel = $this->findWorkspaceAgent($request, $agent);

        $grant->execute($agentModel, $member);

        return $this->buildAgentsPayload($request, $member);
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    public function detachAgent(
        Request $request,
        string $chatter,
        string $agent,
        RevokeAgentMembershipAction $detach,
    ): array {
        abort_unless(has_team_permission('account.members.manage'), 404);

        $member = $this->findChatter($request, $chatter);
        $agentModel = $this->findWorkspaceAgent($request, $agent);

        $detach->execute($agentModel, $member);

        return $this->buildAgentsPayload($request, $member);
    }

    public function resetPassword(
        Request $request,
        string $chatter,
        ResetChatterPasswordAction $reset,
    ): RedirectResponse {
        abort_unless(has_team_permission('account.members.manage'), 404);

        $member = $this->findChatter($request, $chatter);

        $reset->execute($member);

        return back()->with('success', "Password reset link sent to {$member->email}.");
    }

    public function destroy(
        Request $request,
        string $chatter,
        DeleteChatterAction $delete,
    ): RedirectResponse {
        abort_unless(has_team_permission('account.members.manage'), 404);

        $member = $this->findChatter($request, $chatter);

        $delete->execute($member);

        return back()->with('success', "{$member->name} removed.");
    }

    /**
     * @return array{attached: list<array{id: string, name: string}>, available: list<array{id: string, name: string}>}
     */
    private function buildAgentsPayload(Request $request, User $member): array
    {
        // Only protected agents can have members — public agents are open
        // to anyone so per-chatter grants are meaningless.
        $workspaceAgents = $request->user()->effectiveUser()
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

    private function findChatter(Request $request, string $externalId): User
    {
        /** @var User $member */
        $member = $request->user()->effectiveUser()
            ->chatters()
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $member;
    }

    private function findWorkspaceAgent(Request $request, string $externalId): Agent
    {
        /** @var Agent $agent */
        $agent = $request->user()->effectiveUser()
            ->agents()
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $agent;
    }

}
