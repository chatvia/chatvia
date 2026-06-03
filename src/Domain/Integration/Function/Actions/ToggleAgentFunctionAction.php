<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

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
