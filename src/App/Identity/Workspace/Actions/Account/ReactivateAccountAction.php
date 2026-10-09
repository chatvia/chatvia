<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account back on and clear the stored reason. Reactivating a
 * workspace mirrors the deactivation cascade — every member switched off with
 * it is restored, so the account becomes usable again.
 */
class ReactivateAccountAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update([
                'deactivated_at' => null,
                'deactivation_reason' => null,
            ]);

            if ($user->isWorkspace()) {
                $user->users()
                    ->whereNotNull('deactivated_at')
                    ->update([
                        'deactivated_at' => null,
                        'deactivation_reason' => null,
                    ]);
            }
        });
    }
}
