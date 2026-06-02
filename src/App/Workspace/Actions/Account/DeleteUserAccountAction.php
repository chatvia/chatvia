<?php

namespace App\Workspace\Actions\Account;

use App\Workspace\Models\User;

class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
