<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;

class DeleteUserAccountAction
{
    public function execute(User $user): void
    {
        $user->delete();
    }
}
