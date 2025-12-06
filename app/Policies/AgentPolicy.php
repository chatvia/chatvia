<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;
use App\Services\Teams\TeamService;

class AgentPolicy
{
    public function __construct(
        private TeamService $teamService
    ) {}

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
    public function view(User $user, Agent $agent): bool
    {
        // Admin can view all
        if ($user->isAdmin()) {
            return true;
        }

        // Agent owner can view
        if ($user->id === $agent->user_id) {
            return true;
        }

        // Check team-based access
        $team = current_team();
        if ($team && $this->teamService->canAccessAgent($user, $team, $agent)) {
            return true;
        }

        return false;
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
