<?php

namespace Domain\Membership\Team\Actions\Invitations;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * Accepts a team invitation from its signed link. Rejects an expired link.
 * Inside one
 * transaction it either verifies an existing account's password or creates a
 * new teammate account (email pre-verified — the link proves the address),
 * attaches it to the team under the invited role and deletes the invitation.
 *
 * An existing account may only join when it already belongs to the inviting
 * workspace: a person has one workspace, and an account from another workspace
 * would keep that workspace's settings while reaching this one's agents.
 * Deactivated accounts can never join a team. Only the invitee sees why, so
 * the inviter never learns about other workspaces' accounts. (Chatters are
 * not staff accounts at all, so an invitation never finds one.)
 */
class AcceptTeamInvitationAction
{
    /**
     * @throws DomainValidationException
     */
    public function execute(AcceptInvitationData $data): User
    {
        $invitation = $data->invitation;

        if ($invitation->hasExpired()) {
            throw new DomainValidationException('invitation', __('This invitation has expired.'));
        }

        $team = $invitation->team;
        $workspace = $team->workspace;

        return DB::transaction(function () use ($data, $invitation, $team, $workspace): User {
            $existingUser = $invitation->existingAccount();

            if ($existingUser instanceof User) {
                // The password comes first, so a forwarded link alone tells
                // nobody anything about the account behind the email.
                if (! Hash::check($data->password ?? '', $existingUser->password)) {
                    throw new DomainValidationException('password', __('The provided password is incorrect.'));
                }

                if ($existingUser->isDeactivated()) {
                    throw new DomainValidationException('invitation', __('This account has been deactivated.'));
                }

                if ($existingUser->workspaceMembership?->workspace_id !== $workspace->id) {
                    throw new DomainValidationException('invitation', __('This email already has an account in another workspace. Ask for an invitation to a different email.'));
                }

                $user = $existingUser;
            } else {
                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($data->password),
                    'email_verified_at' => now(),
                ]);

                $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Member->value]);

                Event::dispatch(new Registered($user));
            }

            $team->members()->attach($user->id, [
                'role_id' => $invitation->role_id,
            ]);

            $invitation->delete();

            return $user;
        });
    }
}
