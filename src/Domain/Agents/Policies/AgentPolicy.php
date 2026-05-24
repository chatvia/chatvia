<?php

namespace Domain\Agents\Policies;

use Domain\Agents\Models\Agent;
use App\Auth\Models\User;

class AgentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Agent $agent): bool
    {
        return $user->canAccessAgent($agent);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCompany();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }
}
