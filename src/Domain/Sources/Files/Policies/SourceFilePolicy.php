<?php

namespace Domain\Sources\Files\Policies;

use App\Auth\Models\User;
use Domain\Sources\Files\Models\SourceFile;

class SourceFilePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->type === 'admin';
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SourceFile $agentFile): bool
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
    public function update(User $user, SourceFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SourceFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SourceFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SourceFile $agentFile): bool
    {
        return $this->view($user, $agentFile);
    }
}
