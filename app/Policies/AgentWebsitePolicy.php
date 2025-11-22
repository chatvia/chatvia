<?php

namespace App\Policies;

use App\Models\SourceWebsite;
use App\Models\User;

class AgentWebsitePolicy
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
    public function view(User $user, SourceWebsite $agentWebsite): bool
    {
        return $user->id === $agentWebsite->user_id;
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
    public function update(User $user, SourceWebsite $agentWebsite): bool
    {
        return $this->view($user, $agentWebsite);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SourceWebsite $agentWebsite): bool
    {
        return $this->view($user, $agentWebsite);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SourceWebsite $agentWebsite): bool
    {
        return $this->view($user, $agentWebsite);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SourceWebsite $agentWebsite): bool
    {
        return $this->view($user, $agentWebsite);
    }
}
