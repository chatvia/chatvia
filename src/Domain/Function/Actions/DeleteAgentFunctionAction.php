<?php

namespace Domain\Function\Actions;

use App\Workspace\Models\User;

class DeleteAgentFunctionAction
{
    public function execute(User $user, string $externalId): void
    {
        $user->functions()
            ->where('external_id', $externalId)
            ->delete();
    }
}
