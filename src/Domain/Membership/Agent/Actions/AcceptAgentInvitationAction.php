<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

/**
 * Finalize an agent-chatter invitation. The chatter row is already in the DB
 * (provisioned by {@see InviteAgentChatterAction}) with a random password and
 * `email_verified_at = null`; this action turns them from "pending" into
 * "active" once they pick a credential.
 *
 * The invitation row is locked, checked (still there, not expired) and deleted
 * inside the same transaction as the account update, so a link can be used
 * only once even when two accepts race, and a failed accept leaves it usable.
 *
 * Two paths (discriminated on {@see AcceptAgentInvitationData::$kind}):
 *   - Password — set their real password from the form.
 *   - Social   — link an OAuth identity (UserSocial). The provider email
 *                must match the invited email so a forwarded link can't be
 *                redirected to a different account.
 *
 * Both paths mark `email_verified_at = now()` (the invite link in their
 * inbox is the proof of email ownership) and confirm the `agent_user` grant
 * exists. We reject replays: once verified, the link can't be re-used to
 * silently reset the password — the user must go through normal login.
 *
 * @throws AgentChatterInvitationRejectedException
 */
class AcceptAgentInvitationAction
{
    public function execute(AcceptAgentInvitationData $data): User
    {
        $invitedEmail = $data->invitation->email;

        if ($data->kind === InvitationCredentialKind::Social) {
            $socialEmail = (string) $data->socialEmail;
            if (! hash_equals(strtolower($invitedEmail), strtolower($socialEmail))) {
                throw new AgentChatterInvitationRejectedException(
                    'email',
                    __('This invitation was sent to :email. Sign in with the :provider account that uses the same email.', [
                        'email' => $invitedEmail,
                        'provider' => $data->socialProvider?->value,
                    ]),
                );
            }
        }

        return DB::transaction(function () use ($data): User {
            $invitation = $this->lockValidInvitation($data->invitation);

            $chatter = $this->resolveInvitedChatter($invitation->agent, $invitation->email);

            $fields = [
                'name' => $data->name !== '' ? $data->name : $chatter->name,
                'email_verified_at' => now(),
            ];

            if ($data->kind === InvitationCredentialKind::Password) {
                $fields['password'] = Hash::make((string) $data->password);
            }

            $chatter->forceFill($fields)->save();

            if ($data->kind === InvitationCredentialKind::Social) {
                UserSocial::query()->updateOrCreate(
                    ['user_id' => $chatter->id, 'provider' => $data->socialProvider],
                    ['provider_id' => (string) $data->socialProviderId],
                );
            }

            // Single use: the link stops working in the same commit that
            // activates the account.
            $invitation->delete();

            return $chatter;
        });
    }

    /**
     * Re-read the invitation under a row lock and make sure it can still be
     * used. A concurrent accept that already deleted it, an expired link, or a
     * deleted agent all reject.
     */
    private function lockValidInvitation(AgentChatterInvitation $given): AgentChatterInvitation
    {
        /** @var AgentChatterInvitation|null $invitation */
        $invitation = AgentChatterInvitation::query()
            ->whereKey($given->getKey())
            ->where('token', $given->token)
            ->lockForUpdate()
            ->first();

        if ($invitation === null || $invitation->agent === null) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                __('This invitation is no longer valid. Ask the agent owner to send a new one.'),
            );
        }

        if ($invitation->hasExpired()) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                __('This invitation has expired. Ask the agent owner to send a new one.'),
            );
        }

        return $invitation;
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
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                __('This invitation is no longer valid. Ask the agent owner to send a new one.'),
            );
        }

        if ($chatter->email_verified_at !== null) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                __('This invitation has already been used. Sign in with your existing credentials instead.'),
            );
        }

        if (! $agent->chatters()->whereKey($chatter->id)->exists()) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                __('This invitation no longer grants access to this agent.'),
            );
        }

        return $chatter;
    }
}
