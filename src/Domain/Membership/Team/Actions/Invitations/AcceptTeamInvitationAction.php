<?php

namespace Domain\Membership\Team\Actions\Invitations;

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * Accepts a team invitation from its signed link. Rejects an expired link.
 * Inside one
 * transaction it either verifies an existing account's password or creates a
 * new teammate account (email pre-verified — the link proves the address),
 * attaches it to the team under the invited role and deletes the invitation.
 * Chatter accounts can never join a team.
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
        /** @var User $owner */
        $owner = $team->owner;

        return DB::transaction(function () use ($data, $invitation, $team, $owner): User {
            $existingUser = User::where('email', $invitation->email)->first();

            if ($existingUser) {
                if ($existingUser->isChatter()) {
                    throw new DomainValidationException('invitation', __('This account is a chat user and cannot join a team.'));
                }

                if (! Hash::check($data->password ?? '', $existingUser->password)) {
                    throw new DomainValidationException('password', __('The provided password is incorrect.'));
                }

                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $data->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($data->password),
                    'email_verified_at' => now(),
                    'workspace_id' => $owner->id,
                    'type' => UserType::Employee,
                ]);

                event(new Registered($user));
            }

            $team->members()->attach($user->id, [
                'role_id' => $invitation->role_id,
            ]);

            $invitation->delete();

            return $user;
        });
    }
}
