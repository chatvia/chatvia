<?php

namespace Domain\Teams\Actions;

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Teams\Data\InviteTeamMemberData;
use Domain\Teams\Exceptions\InviteRejectedException;

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

        if (! $owner->canAddTeamMember()) {
            throw new InviteRejectedException('email', "You've reached your member limit ({$owner->quota->members_limit}). Upgrade your plan to invite more members.");
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
