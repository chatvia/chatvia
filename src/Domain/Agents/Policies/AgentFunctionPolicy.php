<?php

namespace Domain\Agents\Policies;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentFunction;

class AgentFunctionPolicy
{
    public function view(User $user, AgentFunction $function): bool
    {
        return $user->can('view', $function->agent);
    }

    public function update(User $user, AgentFunction $function): bool
    {
        return $user->can('update', $function->agent);
    }

    public function delete(User $user, AgentFunction $function): bool
    {
        return $user->can('update', $function->agent);
    }
}
