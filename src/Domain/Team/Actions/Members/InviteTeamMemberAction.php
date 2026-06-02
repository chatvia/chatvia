<?php

namespace Domain\Team\Actions\Members;

use App\Workspace\Models\User;
use App\Team\Services\TeamService;
use Domain\Team\Data\InviteTeamMemberData;
use Domain\Team\Exceptions\InviteRejectedException;

class InviteTeamMemberAction
{
    public function __construct(
        private readonly TeamService $teamService,
    ) {}

    /**
     * @throws InviteRejectedException
     */
    public function execute(InviteTeamMemberData $data): void
    {
        $team = $data->team;

        if (! $this->teamService->canInviteMembers($team)) {
            throw new InviteRejectedException('email', 'You need an active subscription to invite team members.');
        }

        /** @var User $owner */
        $owner = $team->owner;

        if (! $owner->canAddWorkspaceUser()) {
            throw new InviteRejectedException('email', "You've reached your workspace user limit ({$owner->quota->workspace_users_limit}), shared between team members and chatters. Upgrade your plan to invite more.");
        }

        if ($data->userId) {
            $existingUser = User::findOrFail($data->userId);

            if ($existingUser->workspace_id !== $data->inviter->id) {
                throw new InviteRejectedException('user_id', 'You can only add users you have invited.');
            }

            if ($team->hasUser($existingUser)) {
                throw new InviteRejectedException('user_id', 'This user is already a member of the team.');
            }

            $role = $team->roles()->where('code', $data->role)->firstOrFail();
            $team->members()->attach($existingUser->id, ['role_id' => $role->id]);

            return;
        }

        if ($team->hasUserWithEmail($data->email)) {
            throw new InviteRejectedException('email', 'This user is already a member of the team.');
        }

        if ($team->invitations()->where('email', $data->email)->exists()) {
            throw new InviteRejectedException('email', 'An invitation has already been sent to this email.');
        }

        $this->teamService->inviteUser($team, $data->email, $data->role);
    }
}
