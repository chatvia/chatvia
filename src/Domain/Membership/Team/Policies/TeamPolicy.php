<?php

namespace Domain\Membership\Team\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may view and manage a team. Viewing needs membership; changing the team,
 * its members and its roles is reserved to the owner, except inviting, which a
 * teammate may do with the `team.invite` permission.
 */
class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(object $user, object $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(object $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add team members (invite).
     *
     * Owners always may; a teammate needs the explicit `team.invite` grant.
     * This is the single source of truth for the invite gate — keep it in sync
     * with nothing, because nothing else should re-implement it.
     */
    public function addTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team) || $user->hasTeamPermission($team, 'team.invite');
    }

    /**
     * Determine whether the user can update team member
     */
    public function updateTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can remove team members.
     */
    public function removeTeamMember(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(object $user, object $team): bool
    {
        return $user->ownsTeam($team);
    }
}
