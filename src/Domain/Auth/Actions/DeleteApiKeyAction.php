<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;

class DeleteApiKeyAction
{
    public function execute(User $user, int $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->delete();
    }
}
