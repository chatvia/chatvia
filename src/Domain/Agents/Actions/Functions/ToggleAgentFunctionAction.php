<?php

namespace Domain\Agents\Actions\Functions;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentFunction;

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
