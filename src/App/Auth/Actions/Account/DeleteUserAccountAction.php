<?php

namespace App\Auth\Actions\Account;

use App\Auth\Models\User;

class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
