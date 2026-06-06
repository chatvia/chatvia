<?php

namespace Domain\Integration\Function\Policies;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Functions are account-level (one row attached to any number of the
 * owner's agents via `agent_function`), so authorization checks ownership
 * of the row, not of any specific agent. Workspace members proxy to the
 * workspace owner via `workspace()`, which matches what
 * `ListAgentFunctionsAction` and the rest of the function endpoints do.
 */
class AgentFunctionPolicy
{
    public function view(User $user, AgentFunction $function): bool
    {
        // Workspace members proxy to the owner — their `workspace_id` is the
        // account that owns the resource. Matches the (protected)
        // `User::workspace()` resolution used internally elsewhere.
        $accountUserId = $user->workspace_id ?? $user->id;

        return $accountUserId === $function->user_id;
    }

    public function update(User $user, AgentFunction $function): bool
    {
        return $this->view($user, $function);
    }

    public function delete(User $user, AgentFunction $function): bool
    {
        return $this->view($user, $function);
    }
}
