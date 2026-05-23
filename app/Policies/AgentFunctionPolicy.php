<?php

namespace App\Policies;

use App\Models\AgentFunction;
use App\Models\User;

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
