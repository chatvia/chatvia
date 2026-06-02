<?php

namespace Domain\Function\Actions;

use App\Workspace\Models\User;
use Domain\Function\Models\AgentFunction;

class ToggleAgentFunctionAction
{
    public function execute(User $user, string $externalId): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->functions()->where('external_id', $externalId)->firstOrFail();

        $function->update(['is_enabled' => ! $function->is_enabled]);

        return $function;
    }
}
