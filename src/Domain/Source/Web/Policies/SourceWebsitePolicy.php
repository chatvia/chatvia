<?php

namespace Domain\Source\Web\Policies;

use App\Workspace\Models\User;
use Domain\Source\Web\Models\SourceWebsite;

class SourceWebsitePolicy
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
