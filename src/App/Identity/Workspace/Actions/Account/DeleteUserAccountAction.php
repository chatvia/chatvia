<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->ownedTeams()->each(fn ($team) => $team->delete());
            $user->delete();
        });
    }
}
