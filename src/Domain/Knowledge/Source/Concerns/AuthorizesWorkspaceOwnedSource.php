<?php

namespace Domain\Knowledge\Source\Concerns;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The policy rules shared by every knowledge source a workspace owns (files,
 * texts, websites).
 *
 * Tenant isolation: a source may only be seen or changed by the workspace that
 * owns it, or by a platform admin. `workspace()` resolves the parent account,
 * so teammates of the owning workspace are allowed too. Without this check any
 * signed-in user could read another tenant's training content by id.
 */
trait AuthorizesWorkspaceOwnedSource
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Model $source): bool
    {
        return $user->isAdmin() || $user->workspace()->id === $source->getAttribute('user_id');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $source): bool
    {
        return $this->view($user, $source);
    }

    public function delete(User $user, Model $source): bool
    {
        return $this->view($user, $source);
    }

    public function restore(User $user, Model $source): bool
    {
        return $this->view($user, $source);
    }

    public function forceDelete(User $user, Model $source): bool
    {
        return $this->view($user, $source);
    }
}
