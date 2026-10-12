<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account back on and clear the stored reason. Reactivating a
 * workspace mirrors the deactivation cascade — every member switched off with
 * it is restored, so the account becomes usable again.
 */
class ReactivateAccountAction
{
    public function execute(Chatter|User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update([
                'deactivated_at' => null,
                'deactivation_reason' => null,
            ]);

            if ($user instanceof User && $user->isWorkspaceOwner()) {
                $workspace = $user->workspace();
                $teammates = User::query()->whereIn('id', $workspace->teammates()->select('users.id'));

                foreach ([$teammates, $workspace->chatters()] as $accounts) {
                    $accounts->whereNotNull('deactivated_at')->update([
                        'deactivated_at' => null,
                        'deactivation_reason' => null,
                    ]);
                }
            }
        });
    }
}
