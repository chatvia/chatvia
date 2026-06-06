<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tear down a whole workspace: the owner, their employees + chatters, their
 * teams, and (by FK cascade) every agent, conversation, knowledge source,
 * credit, billing row, etc. that hangs off them.
 *
 * Only a workspace owner reaches here. Employees and chatters can't own teams,
 * so the teardown never recurses through sub-user-owned teams.
 */
class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        // Cancel any active Stripe subscription immediately. We use cancelNow()
        // (not the grace-period cancel() that UserObserver::deleting would
        // otherwise apply) because the `user_billing` row cascade-deletes below
        // — a grace-period cancel would orphan a still-billing subscription.
        // Null-safe and outside the transaction (external call).
        $user->billing?->subscription()?->cancelNow();

        DB::transaction(function () use ($user): void {
            $ownedTeamIds = $user->ownedTeams()->pluck('id')->all();

            // Employees + chatters belonging to this workspace.
            $subUserIds = $user->users()->pluck('id')->all();

            // Clear team membership first: team_user.team_id, .user_id and
            // .role_id are all RESTRICT, so the pivot blocks deleting the owned
            // teams and any of the workspace's users until these rows are gone.
            DB::table('team_user')
                ->whereIn('team_id', $ownedTeamIds)
                ->orWhereIn('user_id', [...$subUserIds, $user->id])
                ->delete();

            // Owned teams — agents, roles, and invitations cascade off each team.
            $user->ownedTeams()->each(fn ($team) => $team->delete());

            // Workspace sub-users — their owned data cascades on delete. Model
            // delete (not a bulk query) so UserObserver/HasRoles cleanup fires;
            // eager-load `billing` because UserObserver::deleting() reads it
            // (sub-users have none, but the access must not lazy-load).
            $user->users()->with('billing')->get()->each(fn ($subUser) => $subUser->delete());

            // Finally the owner; every remaining reference cascades or nulls out.
            $user->delete();
        });
    }
}
