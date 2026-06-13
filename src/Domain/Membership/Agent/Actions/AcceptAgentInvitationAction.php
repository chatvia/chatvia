<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Data\AcceptAgentInvitationData;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Enums\User\UserType;

/**
 * Finalize a signed agent-member invitation. The chatter row is already in
 * the DB (provisioned by {@see InviteAgentChatterAction}) with a random
 * password and `email_verified_at = null`; this action just turns them from
 * "pending" into "active" once they pick a credential.
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
        if ($data->kind === InvitationCredentialKind::Social) {
            $socialEmail = (string) $data->socialEmail;
            if (! hash_equals(strtolower($data->invitedEmail), strtolower($socialEmail))) {
                throw new AgentChatterInvitationRejectedException(
                    'email',
                    "This invitation was sent to {$data->invitedEmail}. Sign in with the {$data->socialProvider?->value} account that uses the same email.",
                );
            }
        }

        return DB::transaction(function () use ($data): User {
            $chatter = $this->resolveInvitedChatter($data->agent, $data->invitedEmail);

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
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                'This invitation is no longer valid. Ask the agent owner to send a new one.',
            );
        }

        if ($chatter->email_verified_at !== null) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                'This invitation has already been used. Sign in with your existing credentials instead.',
            );
        }

        if (! $agent->chatters()->whereKey($chatter->id)->exists()) {
            throw new AgentChatterInvitationRejectedException(
                'invitation',
                'This invitation no longer grants access to this agent.',
            );
        }

        return $chatter;
    }
}
