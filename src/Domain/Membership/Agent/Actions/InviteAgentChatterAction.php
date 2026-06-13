<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Enums\User\UserSource;
use Support\Enums\User\UserType;

/**
 * Provision a chatter for an agent in one step:
 *
 *   1. Find-or-create the chatter `User` row (type=chatter, random password,
 *      `email_verified_at` left null until they accept).
 *   2. Attach them to this agent via the `agent_user` pivot — without it
 *      `User::canAccessAgent()` rejects them, so the sign-in link is useless.
 *   3. Insert a `agent_chatter_invitations` row carrying a random `token` + expiry,
 *      then email the recipient a `/chat-invitation/{token}` link. The row is
 *      deleted once the invitation is accepted.
 *
 * Idempotent: re-inviting the same email drops the previous pending invitation
 * row and mints a fresh token, so a forwarded old link can't be replayed.
 *
 * Rejects emails owned by a non-chatter (workspace owner, employee, admin) and
 * emails owned by a chatter under a different workspace — the chatter email
 * column is globally unique.
 *
 * @throws AgentChatterInvitationRejectedException
 */
class InviteAgentChatterAction
{
    private const TTL_DAYS = 7;

    public function execute(InviteAgentChatterData $data): AgentChatterInvitationData
    {
        $agent = $data->agent;
        $email = strtolower(trim($data->email));

        /** @var AgentChatterInvitation $invitation */
        $invitation = DB::transaction(function () use ($agent, $email): AgentChatterInvitation {
            $owner = $agent->user;

            /** @var User|null $existing */
            $existing = User::query()->where('email', $email)->first();

            if ($existing !== null) {
                if ($existing->type !== UserType::Chatter) {
                    throw new AgentChatterInvitationRejectedException(
                        'email',
                        'This email already belongs to a different kind of account and cannot be invited as a chat member.',
                    );
                }

                if ($existing->workspace_id !== $owner->id) {
                    throw new AgentChatterInvitationRejectedException(
                        'email',
                        'This email is already used by a chatter under a different workspace.',
                    );
                }

                $chatter = $existing;
            } else {
                $chatter = User::create([
                    'name' => $this->derivedNameFromEmail($email),
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                    'type' => UserType::Chatter,
                    'source' => UserSource::Dashboard,
                    'workspace_id' => $owner->id,
                    'email_verified_at' => null,
                ]);
            }

            if (! $agent->chatters()->whereKey($chatter->id)->exists()) {
                $agent->chatters()->attach($chatter->id);
            }

            AgentChatterInvitation::query()
                ->where('agent_id', $agent->id)
                ->where('email', $email)
                ->delete();

            return AgentChatterInvitation::create([
                'agent_id' => $agent->id,
                'email' => $email,
                'token' => Str::random(64),
                'expires_at' => Carbon::now()->addDays(self::TTL_DAYS),
            ]);
        });

        $acceptUrl = route('agents.invitation.show', ['token' => $invitation->token]);

        AgentChatterInvited::dispatch($agent, $email, $acceptUrl);

        return new AgentChatterInvitationData(
            email: $email,
            accept_url: $acceptUrl,
            expires_at: $invitation->expires_at,
        );
    }

    private function derivedNameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');
        $clean = trim(str_replace(['.', '_', '-', '+'], ' ', $local));

        return Str::headline($clean) ?: 'Member';
    }
}
