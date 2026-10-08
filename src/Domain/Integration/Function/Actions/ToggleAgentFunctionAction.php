<?php

namespace Domain\Integration\Function\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Flip a workspace function's enabled flag. Looked up in the acting user's
 * workspace so teammates manage the same functions the owner sees.
 */
class ToggleAgentFunctionAction
{
    public function execute(User $user, string $externalId): AgentFunction
    {
        /** @var AgentFunction $function */
        $function = $user->workspace()->functions()->where('external_id', $externalId)->firstOrFail();

        $function->update(['is_enabled' => ! $function->is_enabled]);

        return $function;
    }
}
