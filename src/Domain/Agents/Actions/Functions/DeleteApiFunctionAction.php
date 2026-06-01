<?php

namespace Domain\Agents\Actions\Functions;

use Domain\Agents\Models\AgentFunction;

/**
 * Delete an account-level function through the public API. The model is
 * resolved by route binding in the controller, so this action just deletes —
 * detachment from any attached agents cascades via the pivot.
 */
class DeleteApiFunctionAction
{
    public function execute(AgentFunction $function): void
    {
        $function->delete();
    }
}
