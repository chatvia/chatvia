<?php

namespace App\Identity\ApiKey\Actions;

use App\Identity\Workspace\Models\User;

class DeleteApiKeyAction
{
    public function execute(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
