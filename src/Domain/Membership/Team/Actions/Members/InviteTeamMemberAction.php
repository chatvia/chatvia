<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Support\Exceptions\DomainValidationException;
use Domain\Membership\Team\Services\TeamService;

class InviteTeamMemberAction
{
    public function __construct(
        private readonly TeamService $teamService,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(InviteTeamMemberData $data): void
    {
        $team = $data->team;

        if (! $this->teamService->canInviteMembers($team)) {
            throw new DomainValidationException('email', 'You need an active subscription to invite team members.');
        }

        /** @var User $owner */
        $owner = $team->owner;

        if (! $owner->canAddWorkspaceUser()) {
            throw new DomainValidationException('email', "You've reached your workspace user limit ({$owner->quota->workspace_users_limit}), shared between team members and chatters. Upgrade your plan to invite more.");
        }

        // Duplicate detection (already-a-member / already-invited) lives in
        // InviteTeamMemberRequest's rules, backed by the `team_user` composite
        // key and the `team_invitations` unique index. The Action keeps only
        // the owner-side business gates above and the workspace-scoping check.
        if ($data->userId) {
            $existingUser = User::findOrFail($data->userId);

            if ($existingUser->workspace_id !== $data->inviter->id) {
                throw new DomainValidationException('user_id', 'You can only add users you have invited.');
            }

            $role = $team->roles()->where('code', $data->role)->firstOrFail();
            $team->members()->attach($existingUser->id, ['role_id' => $role->id]);

            return;
        }

        $this->teamService->inviteUser($team, $data->email, $data->role);
    }
}
