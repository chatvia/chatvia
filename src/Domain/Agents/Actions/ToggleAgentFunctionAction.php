<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentFunction;

class ToggleAgentFunctionAction
{
    public function execute(User $user, string $externalId): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->functions()->where('external_id', $externalId)->firstOrFail();

        $function->update(['enabled' => ! $function->enabled]);

        return $function;
    }
}
