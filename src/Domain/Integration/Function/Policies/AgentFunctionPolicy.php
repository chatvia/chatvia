<?php

declare(strict_types=1);

namespace Domain\Integration\Function\Policies;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Functions are account-level (one row attached to any number of the
 * owner's agents via `agent_function`), so authorization checks ownership
 * of the row, not of any specific agent. Teammates act for the workspace
 * owner, so the check compares against `User::workspace()`.
 */
class AgentFunctionPolicy
{
    public function view(User $user, AgentFunction $function): bool
    {
        return $function->user_id === $user->workspace()->id;
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
