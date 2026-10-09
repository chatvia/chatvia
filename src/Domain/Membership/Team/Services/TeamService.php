<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Collection;

/**
 * The Membership\Team entry points other contexts call (registration, billing
 * trial provisioning, the agent gate, the dashboard's shared props, seeders and
 * tests). Every method only delegates — the behaviour lives in
 * {@see CreateTeamAction}, {@see TeamAgentAccess} and {@see TeamRoleResolver}.
 * Code inside this context should use those classes directly.
 */
class TeamService
{
    public function __construct(
        private readonly CreateTeamAction $createTeam,
        private readonly TeamAgentAccess $agentAccess,
        private readonly TeamRoleResolver $roles,
    ) {}

    /**
     * Create the user's first team ("{name}'s Team") with its default roles.
     */
    public function createDefaultTeam(User $user): Team
    {
        return $this->createTeam->execute(new CreateTeamData(
            owner: $user,
            name: $user->name."'s Team",
        ));
    }

    /** @see TeamAgentAccess::canAccess() */
    public function canAccessAgent(User $user, Team $team, Agent $agent): bool
    {
        return $this->agentAccess->canAccess($user, $team, $agent);
    }

    /**
     * @see TeamAgentAccess::accessibleAgents()
     *
     * @return Collection<int, Agent>
     */
    public function accessibleAgents(User $user, Team $team): Collection
    {
        return $this->agentAccess->accessibleAgents($user, $team);
    }

    /**
     * @see TeamRoleResolver::permissionsByTeam()
     *
     * @return array<int, list<string>>
     */
    public function userPermissionsMap(User $user): array
    {
        return $this->roles->permissionsByTeam($user);
    }
}
