<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account off with an optional reason (shown to admins on the Members
 * page). Idempotent — re-deactivating refreshes the reason.
 *
 * Deactivating a workspace owner is an account-wide off switch: every member it
 * owns (employees + chatters) is switched off alongside the owner so they lose
 * access too. Members already off keep their original timestamp. Deactivating a
 * single member (chatter/employee) does not cascade.
 */
class DeactivateAccountAction
{
    public function execute(User $user, ?string $reason = null): void
    {
        $now = now();

        DB::transaction(function () use ($user, $reason, $now): void {
            $user->update([
                'deactivated_at' => $now,
                'deactivation_reason' => $reason,
            ]);

            if ($user->isWorkspace()) {
                $user->users()
                    ->whereNull('deactivated_at')
                    ->update([
                        'deactivated_at' => $now,
                        'deactivation_reason' => $reason,
                    ]);
            }
        });
    }
}
