<?php

namespace Domain\Agent\Policies;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentAccessPolicy;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Models\Team;
use Illuminate\Auth\Access\Response;

/**
 * Who may manage agents from the dashboard and the public API.
 *
 * Viewing, updating and deleting an agent follow the one dashboard access rule
 * ({@see AgentAccessPolicy::canManage()}, also the `access-agent` Gate): a
 * workspace owner (platform staff included) reaches their own agents, a
 * teammate the agents their team role reaches, a chatter none. The finer per-feature team permissions
 * (`agent.setup`, `agent.delete`, …) are route middleware on top.
 *
 * Creating an agent places it in a team, so only that team's owner may.
 */
class AgentPolicy
{
    public function __construct(
        private readonly AgentAccessPolicy $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Agent $agent): bool
    {
        return $this->access->canManage($user, $agent);
    }

    /**
     * Without a team there is nowhere to put the agent — a not-found, as the
     * dashboard has no team selected yet.
     */
    public function create(User $user, ?Team $team = null): Response
    {
        if (! $team instanceof Team) {
            return Response::denyAsNotFound(__('No team found. Please select or create a team first.'));
        }

        return $user->ownsTeam($team)
            ? Response::allow()
            : Response::deny(__('Only team owners can create agents.'));
    }

    public function update(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $this->view($user, $agent);
    }
}
