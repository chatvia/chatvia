<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account off with an optional reason (shown to admins on the Members
 * page). Idempotent — re-deactivating refreshes the reason.
 *
 * Deactivating a workspace owner is an account-wide off switch: every member it
 * owns (employees + chatters) is switched off alongside the owner so they lose
 * access too, and the workspace's agents stop answering on every surface and
 * channel ({@see WorkspaceActivation}). Members already off keep their original
 * timestamp. Deactivating a single member (chatter/employee) does not cascade.
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
            $user->update([
                'deactivated_at' => $now,
                'deactivation_reason' => $reason,
            ]);

            if ($workspace instanceof Workspace) {
                $teammates = User::query()->whereIn('id', $workspace->teammates()->select('users.id'));

                foreach ([$teammates, $workspace->chatters()] as $accounts) {
                    $accounts->whereNull('deactivated_at')->update([
                        'deactivated_at' => $now,
                        'deactivation_reason' => $reason,
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
