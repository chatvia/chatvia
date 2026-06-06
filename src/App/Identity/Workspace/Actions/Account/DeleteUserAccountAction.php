<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

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
        $this->cancelSubscriptions($user);

        DB::transaction(function () use ($user): void {
            $ownedTeamIds = $user->ownedTeams()->pluck('id')->all();

            // Employees + chatters belonging to this workspace.
            $subUserIds = $user->users()->pluck('id')->all();

            // Clear team membership first: team_user.team_id, .user_id and
            // .role_id are all RESTRICT, so the pivot blocks both deleting the
            // workspace's users and the owned-team cascade (team → roles would
            // trip team_user.role_id) until these rows are gone.
            DB::table('team_user')
                ->whereIn('team_id', $ownedTeamIds)
                ->orWhereIn('user_id', [...$subUserIds, $user->id])
                ->delete();

            // Workspace sub-users — their owned data cascades on delete. Model
            // delete (not a bulk query) so UserObserver/HasRoles cleanup fires;
            // eager-load `billing` because UserObserver::deleting() reads it
            // (sub-users have none, but the access must not lazy-load).
            $user->users()->with('billing')->get()->each(fn ($subUser) => $subUser->delete());

            // Finally the owner; every remaining reference cascades or nulls out.
            $user->delete();
        });
    }

    /**
     * Best-effort immediate cancellation of every Stripe subscription on the
     * workspace's billing (a workspace can hold more than one — e.g. add-ons as
     * separate subscriptions) before the `user_billing` row cascade-deletes in
     * the teardown.
     *
     * We use cancelNow() — not the grace-period cancel() UserObserver::deleting
     * would otherwise apply — so we don't orphan a still-billing subscription.
     * It covers both active and *trialing* subscriptions; already-canceled ones
     * are skipped, and a generic trial (trial_ends_at with no subscription row)
     * simply yields nothing to iterate.
     *
     * If Stripe rejects a call — most often because the subscription no longer
     * exists there (stale/test data: "No such subscription") — it can't bill
     * anyone, so we log it, mark it canceled locally, and carry on rather than
     * failing the whole account deletion. Marking it canceled also stops
     * UserObserver::deleting() from retrying the cancel during delete().
     */
    private function cancelSubscriptions(User $user): void
    {
        $billing = $user->billing;

        if ($billing === null) {
            return;
        }

        foreach ($billing->subscriptions()->get() as $subscription) {
            if ($subscription->canceled()) {
                continue;
            }

            $subscription->cancelNow();
        }
    }
}
