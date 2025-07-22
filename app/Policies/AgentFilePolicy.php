<?php

namespace App\Policies;

use App\Models\AgentFile;
use App\Models\User;

class AgentFilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AgentFile $agentFile): bool
    {
        return $user->id === $agentFile->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AgentFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AgentFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AgentFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AgentFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }
}
