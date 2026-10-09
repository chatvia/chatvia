<?php

namespace Domain\Agent\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\QueryBuilders\AgentQueryBuilder;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;

/**
 * Which agents a user's agent list shows — one rule for the dashboard and the
 * public API. A workspace owner sees every agent in the teams they own; a
 * teammate sees the agents their role reaches in the team they work in.
 * Callers narrow it further (the dashboard to the selected team, the API to
 * an optional `team_id`).
 */
final readonly class AgentVisibility
{
    public function __construct(
        private TeamService $teams,
    ) {}

    /**
     * @return AgentQueryBuilder<Agent>
     */
    public function query(User $user, ?Team $currentTeam): AgentQueryBuilder
    {
        $granted = $currentTeam !== null && ! $user->ownsTeam($currentTeam)
            ? $this->teams->accessibleAgents($user, $currentTeam)->pluck('id')->all()
            : [];

        return Agent::query()->where(fn (AgentQueryBuilder $query) => $query
            ->whereIn('team_id', $user->ownedTeams()->select('id'))
            ->orWhereIn('id', $granted));
    }
}
