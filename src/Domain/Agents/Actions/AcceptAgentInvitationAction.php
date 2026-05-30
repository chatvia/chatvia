<?php

namespace Domain\Agents\Actions;

use App\Auth\Models\User;
use App\Auth\Models\UserSocial;
use Domain\Agents\Exceptions\ChatterInvitationRejectedException;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\SocialProvider;
use Support\Enums\UserType;

/**
 * Finalize a signed agent-member invitation. The chatter row is already in
 * the DB (provisioned by {@see InviteAgentMemberAction}) with a random
 * password and `email_verified_at = null`; this action just turns them from
 * "pending" into "active" once they pick a credential.
 *
 * Two paths:
 *   - `password` — set their real password from the form.
 *   - `social`   — link an OAuth identity (UserSocial). The provider email
 *                  must match the invited email so a forwarded link can't be
 *                  redirected to a different account.
 *
 * Both paths mark `email_verified_at = now()` (the invite link in their
 * inbox is the proof of email ownership) and confirm the `agent_user` grant
 * exists. We reject replays: once verified, the link can't be re-used to
 * silently reset the password — the user must go through normal login.
 *
 * @throws ChatterInvitationRejectedException
 */
class AcceptAgentInvitationAction
{
    public function executeWithPassword(
        Agent $agent,
        string $email,
        string $name,
        string $password,
    ): User {
        return DB::transaction(function () use ($agent, $email, $name, $password): User {
            $chatter = $this->resolveInvitedChatter($agent, $email);

            $chatter->forceFill([
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();

            return $chatter;
        });
    }

    public function executeWithSocial(
        Agent $agent,
        string $invitedEmail,
        string $socialEmail,
        string $socialName,
        SocialProvider $provider,
        string $providerId,
    ): User {
        if (! hash_equals(strtolower($invitedEmail), strtolower($socialEmail))) {
            throw new ChatterInvitationRejectedException(
                'email',
                "This invitation was sent to {$invitedEmail}. Sign in with the {$provider->value} account that uses the same email.",
            );
        }

        return DB::transaction(function () use ($agent, $invitedEmail, $socialName, $provider, $providerId): User {
            $chatter = $this->resolveInvitedChatter($agent, $invitedEmail);

            $chatter->forceFill([
                'name' => $socialName !== '' ? $socialName : $chatter->name,
                'email_verified_at' => now(),
            ])->save();

            UserSocial::query()->updateOrCreate(
                ['user_id' => $chatter->id, 'provider' => $provider],
                ['provider_id' => $providerId],
            );

            return $chatter;
        });
    }

    private function resolveInvitedChatter(Agent $agent, string $email): User
    {
        /** @var User|null $chatter */
        $chatter = User::query()
            ->where('email', strtolower(trim($email)))
            ->where('type', UserType::Chatter)
            ->where('workspace_id', $agent->user_id)
            ->first();

        if ($chatter === null) {
            throw new ChatterInvitationRejectedException(
                'invitation',
                'This invitation is no longer valid. Ask the agent owner to send a new one.',
            );
        }

        if ($chatter->email_verified_at !== null) {
            throw new ChatterInvitationRejectedException(
                'invitation',
                'This invitation has already been used. Sign in with your existing credentials instead.',
            );
        }

        if (! $agent->chatters()->whereKey($chatter->id)->exists()) {
            throw new ChatterInvitationRejectedException(
                'invitation',
                'This invitation no longer grants access to this agent.',
            );
        }

        return $chatter;
    }
}
