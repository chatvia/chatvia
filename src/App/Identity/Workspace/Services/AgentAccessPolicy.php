<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Policies\AgentPolicy;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Collection;

/**
 * The one rule for who may use an agent. It answers two questions:
 *
 *  - {@see canManage()}: may the user work on the agent from the dashboard —
 *    settings, conversations, live events, taking over a chat? This is the
 *    `access-agent` Gate and {@see AgentPolicy}.
 *  - {@see canChat()}: may the user talk to the agent on its hosted chat?
 *    This is `User::canAccessAgent()`, used by the chat sign-in paths.
 *
 * The cases, by account type:
 *
 *  | Account                 | manage                     | chat                    |
 *  |-------------------------|----------------------------|-------------------------|
 *  | Admin (platform staff)  | every agent                | every agent             |
 *  | Workspace owner         | own agents only            | own agents only         |
 *  | Teammate (employee)     | agents of their team that  | same as manage          |
 *  |                         | their team role reaches    |                         |
 *  | Chatter                 | never                      | agents granted to them  |
 *
 * A teammate's team is their current team, or their first team when none is
 * selected yet. The agent must belong to that team: owning a team says nothing
 * about agents of another workspace.
 */
final readonly class AgentAccessPolicy
{
    public function __construct(
        private TeamService $teams,
    ) {}

    public function canManage(User $user, Agent $agent): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isWorkspace() => $agent->user_id === $user->id,
            $user->isEmployee() => $this->teammateReaches($user, $agent),
            default => false,
        };
    }

    public function canChat(User $user, Agent $agent): bool
    {
        if ($user->isChatter()) {
            return $user->accessibleAgents()->whereKey($agent->id)->exists();
        }

        return $this->canManage($user, $agent);
    }

    /**
     * Every agent the user may chat with, for the chat agent switcher. Kept in
     * step with {@see canChat()} so the list never shows an agent the switch
     * would then refuse. A workspace account lists its own agents only (staff
     * included).
     *
     * @return Collection<int, Agent>
     */
    public function chattableAgents(User $user): Collection
    {
        if ($user->isChatter()) {
            return $user->accessibleAgents()->get()->toBase();
        }

        if ($user->isWorkspace()) {
            return $user->agents()->get()->toBase();
        }

        $team = $this->teamOf($user);

        return $user->isEmployee() && $team instanceof Team
            ? $this->teams->accessibleAgents($user, $team)->toBase()
            : collect();
    }

    private function teammateReaches(User $user, Agent $agent): bool
    {
        $team = $this->teamOf($user);

        return $team instanceof Team
            && $agent->team_id === $team->id
            && $this->teams->canAccessAgent($user, $team, $agent);
    }

    /**
     * Read-only: never writes `current_team_id`, so it is safe in Gate checks
     * and broadcast channel authorization.
     */
    private function teamOf(User $user): ?Team
    {
        return $user->currentOrPrimaryTeam();
    }
}
