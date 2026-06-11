<?php

namespace Domain\Knowledge\Source\File\Policies;

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\File\Models\SourceFile;

class SourceFilePolicy
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
     *
     * Tenant isolation: a knowledge-base file may only be seen by the
     * workspace that owns it (or a platform admin). workspace() resolves
     * the parent account, so teammates of the owning workspace are allowed.
     * Without this any authenticated user could read another tenant's
     * proprietary training documents by id (cross-tenant IDOR).
     */
    public function view(User $user, SourceFile $agentFile): bool
    {
        return $user->isAdmin() || $user->workspace()->id === $agentFile->user_id;
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
