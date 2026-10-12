<?php

namespace Domain\Membership\Team\Actions\Members;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Invitations\CreateTeamInvitationAction;
use Domain\Membership\Team\Data\InviteTeamMemberData;
use Support\Exceptions\DomainValidationException;

/**
 * Adds someone to a team from the team page. Two paths: a user the inviter
 * already manages is attached straight away under the chosen role; anyone else
 * gets an emailed invitation ({@see CreateTeamInvitationAction}).
 */
class InviteTeamMemberAction
{
    public function __construct(
        private readonly CreateTeamInvitationAction $createTeamInvitation,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(InviteTeamMemberData $data): void
    {
        $team = $data->team;

        // Duplicate detection (already-a-member / already-invited) lives in
        // InviteTeamMemberRequest's rules, backed by the `team_user` composite
        // key and the `team_invitations` unique index. The Action keeps only
        // the workspace-scoping check.
        if ($data->userId) {
            $existingUser = User::findOrFail($data->userId);

            if (! $existingUser->isTeammate() || $existingUser->workspace()->id !== $data->inviter->workspace()->id) {
                throw new DomainValidationException('user_id', __('You can only add users you have invited.'));
            }

            $role = $team->roles()->where('code', $data->role)->firstOrFail();
            $team->members()->attach($existingUser->id, ['role_id' => $role->id]);

            return;
        }

        $this->createTeamInvitation->execute($team, $data->email, $data->role);
    }
}
