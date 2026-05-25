<?php

namespace Domain\Sources\Text\Policies;

use App\Auth\Models\User;
use Domain\Sources\Text\Models\SourceText;

class SourceTextPolicy
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
    public function view(User $user, SourceText $sourceText): bool
    {
        return $user->id === $sourceText->user_id;
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
    public function update(User $user, SourceText $sourceText): bool
    {
        return $this->view($user, $sourceText);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SourceText $sourceText): bool
    {
        return $this->view($user, $sourceText);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SourceText $sourceText): bool
    {
        return $this->view($user, $sourceText);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SourceText $sourceText): bool
    {
        return $this->view($user, $sourceText);
    }
}
