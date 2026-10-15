<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Illuminate\Support\Facades\DB;

/**
 * Switch an account back on and clear the stored reason. Reactivating a
 * workspace mirrors the deactivation cascade — every member switched off with
 * it is restored and its agents answer again ({@see WorkspaceActivation}), so
 * the account becomes usable again. The cascade stamps its members with the
 * owner's `deactivated_at`, so a member switched off on their own (another
 * time) stays off.
 */
class ReactivateAccountAction
{
    public function __construct(
        private readonly WorkspaceActivation $activation,
    ) {}

    public function execute(Chatter|User $user): void
    {
        $workspace = $user instanceof User && $user->isWorkspaceOwner() ? $user->workspace() : null;
        $offSince = $user->deactivated_at;

        DB::transaction(function () use ($user, $workspace, $offSince): void {
            $user->update([
                'deactivated_at' => null,
                'deactivation_reason' => null,
            ]);

            if ($workspace instanceof Workspace && $offSince !== null) {
                $teammates = User::query()->whereIn('id', $workspace->teammates()->select('users.id'));

                foreach ([$teammates, $workspace->chatters()] as $accounts) {
                    $accounts->where('deactivated_at', $offSince)->update([
                        'deactivated_at' => null,
                        'deactivation_reason' => null,
                    ]);
                }
            }
        });

        if ($workspace instanceof Workspace) {
            $this->activation->forget($workspace->id);
        }
    }
}
