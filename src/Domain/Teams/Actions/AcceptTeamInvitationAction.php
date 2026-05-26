<?php

namespace Domain\Teams\Actions;

use App\Auth\Models\User;
use Domain\Teams\Data\AcceptInvitationData;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcceptTeamInvitationAction
{
    /**
     * @throws InviteRejectedException
     */
    public function execute(AcceptInvitationData $data): User
    {
        $invitation = $data->invitation;

        if ($invitation->created_at->addDays(7)->isPast()) {
            throw new InviteRejectedException('invitation', 'This invitation has expired.');
        }

        $team = $invitation->team;
        /** @var User $owner */
        $owner = $team->owner;

        if (! $owner->subscribed() && ! $owner->onTrial()) {
            throw new InviteRejectedException('invitation', 'The team owner does not have an active subscription.');
        }

        if (! $owner->canAddTeamMember()) {
            throw new InviteRejectedException('invitation', 'The team owner has reached their member limit. Please contact them to upgrade their plan.');
        }

        return DB::transaction(function () use ($data, $invitation, $team, $owner): User {
            $existingUser = User::where('email', $invitation->email)->first();

            if ($existingUser) {
                if (! Hash::check($data->password ?? '', $existingUser->password)) {
                    throw new InviteRejectedException('password', 'The provided password is incorrect.');
                }

                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $data->name,
                    'email' => $invitation->email,
                    'password' => Hash::make($data->password),
                    'email_verified_at' => now(),
                    'managed_by_user_id' => $owner->id,
                    'type' => 'staff',
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
