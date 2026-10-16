<?php

declare(strict_types=1);

namespace Domain\Notification\Services;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\TeamPermissionPolicy;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who in a workspace should hear about something: the active staff accounts
 * whose team role holds a permission — on one agent, or workspace-wide. The
 * rules are the dashboard's own ({@see TeamPermissionPolicy}), so a
 * notification only reaches someone who could open the page it links to.
 * Whether each of them wants it is their own switch, checked when it is sent.
 */
final readonly class NotificationRecipients
{
    public function __construct(
        private TeamPermissionPolicy $permissions,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function forAgent(Agent $agent, string $permission): Collection
    {
        return $this->members($agent->workspace_id)
            ->filter(fn (User $user): bool => $this->permissions->allowsOnAgent($user, $agent, $permission))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function forWorkspace(int $workspaceId, string $permission): Collection
    {
        return $this->members($workspaceId)
            ->filter(fn (User $user): bool => $this->permissions->allows($user, $permission))
            ->values();
    }

    /**
     * The workspace's active members. The permission checks walk each
     * member's membership and teams, so those relations load for all members
     * at once.
     *
     * @return Collection<int, User>
     */
    private function members(int $workspaceId): Collection
    {
        return User::query()
            ->whereHas('workspaceMembership', fn (Builder $query): Builder => $query->where('workspace_id', $workspaceId))
            ->whereNull('deactivated_at')
            ->orderBy('id')
            ->get()
            ->withRelationshipAutoloading();
    }
}
