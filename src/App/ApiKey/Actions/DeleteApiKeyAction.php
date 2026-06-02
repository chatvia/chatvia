<?php

namespace App\ApiKey\Actions;

use App\Workspace\Models\User;

class DeleteApiKeyAction
{
    public function execute(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
