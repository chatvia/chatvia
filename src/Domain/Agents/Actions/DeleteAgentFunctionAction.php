<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;

class DeleteAgentFunctionAction
{
    public function execute(User $user, string $externalId): void
    {
        $user->functions()
            ->where('external_id', $externalId)
            ->delete();
    }
}
