<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;

class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
