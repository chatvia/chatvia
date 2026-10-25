<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Switch an account off with an optional reason (shown to admins on the Members
 * page). Idempotent — re-deactivating refreshes the reason.
 *
 * Deactivating a workspace owner is an account-wide off switch: every member it
 * owns (employees + chatters) is switched off alongside the owner so they lose
 * access too, and the workspace's agents stop answering on every surface and
 * channel ({@see WorkspaceActivation}). Members already off keep their original
 * timestamp. Deactivating a single member (chatter/employee) does not cascade.
 *
 * Open sessions end on their next request: the dashboard (EnsureCanAccessDashboard),
 * the hosted chat (LogoutDeactivatedUser) and the widget (its session tokens)
 * check the switch every time. Every account switched off also gets a new
 * remember-me token, so a "remember me" cookie from before never signs it in
 * again, not even after it is reactivated.
 */
class DeactivateAccountAction
{
    public function __construct(
        private readonly WorkspaceActivation $activation,
    ) {}

    public function execute(Chatter|User $user, ?string $reason = null): void
    {
        $now = now();
        $workspace = $user instanceof User && $user->isWorkspaceOwner() ? $user->workspace() : null;

        DB::transaction(function () use ($user, $workspace, $reason, $now): void {
            $user->forceFill([
                'deactivated_at' => $now,
                'deactivation_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            if ($workspace instanceof Workspace) {
                $teammates = User::query()->whereIn('id', $workspace->teammates()->select('users.id'));

                foreach ([$teammates, $workspace->chatters()] as $accounts) {
                    $accounts->whereNull('deactivated_at')->update([
                        'deactivated_at' => $now,
                        'deactivation_reason' => $reason,
                        // One random token per row, made by the database.
                        'remember_token' => DB::raw('gen_random_uuid()::text'),
                    ]);
                }
            }
        });

        // After the commit, so a turn running meanwhile cannot cache the old answer again.
        if ($workspace instanceof Workspace) {
            $this->activation->forget($workspace->id);
        }
    }
}
