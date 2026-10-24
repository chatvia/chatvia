<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Chatter\Enums\ChatterSource;
use App\Identity\Chatter\Models\Chatter;
use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Domain\Membership\Agent\Events\AgentChatterInvited;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provision a chatter for an agent in one step:
 *
 *   1. Find-or-create the workspace's chatter with that email (no password,
 *      `email_verified_at` left null until they accept).
 *   2. Attach them to this agent via the `agent_chatter` pivot — without it
 *      `Chatter::canAccessAgent()` rejects them, so the sign-in link is useless.
 *   3. Insert a `agent_chatter_invitations` row carrying a random `token` + expiry,
 *      then fire {@see AgentChatterInvited}; its queued listener emails the
 *      recipient the accept link once the transaction commits. The row is
 *      deleted once the invitation is accepted.
 *
 * Idempotent: re-inviting the same email drops the previous pending invitation
 * row and mints a fresh token, so a forwarded old link can't be replayed.
 *
 * Rejects a public agent (anyone with the link can chat, so there is nothing
 * to invite to). The email is only ever looked up inside this workspace's
 * chatters: chatter emails are unique per workspace, so a staff account or a
 * chatter of another workspace with the same email is a different person to
 * this workspace, and the answer never reveals that they exist.
 *
 * @throws AgentChatterInvitationRejectedException
 */
class InviteAgentChatterAction
{
    public function execute(InviteAgentChatterData $data): AgentChatterInvitationData
    {
        $agent = $data->agent;
        $email = Str::lower(Str::trim($data->email));

        if ($agent->is_public) {
            throw new AgentChatterInvitationRejectedException(
                'email',
                __('This agent is public — anyone with the link can chat with it. No invitation is needed.'),
            );
        }

        /** @var AgentChatterInvitation $invitation */
        $invitation = DB::transaction(function () use ($agent, $email): AgentChatterInvitation {
            $chatter = Chatter::query()
                ->where('workspace_id', $agent->workspace_id)
                ->whereRaw('lower(email) = ?', [$email])
                ->first()
                ?? Chatter::query()->create([
                    'workspace_id' => $agent->workspace_id,
                    'name' => $this->derivedNameFromEmail($email),
                    'email' => $email,
                    'source' => ChatterSource::Dashboard,
                    'email_verified_at' => null,
                ]);

            if (! $agent->chatters()->whereKey($chatter->id)->exists()) {
                $agent->chatters()->attach($chatter->id, ['workspace_id' => $agent->workspace_id]);
            }

            AgentChatterInvitation::query()
                ->where('agent_id', $agent->id)
                ->where('email', $email)
                ->delete();

            return AgentChatterInvitation::create([
                'agent_id' => $agent->id,
                'email' => $email,
                'token' => Str::random(64),
                'expires_at' => Carbon::now()->addDays(AgentChatterInvitation::TTL_DAYS),
            ]);
        });

        AgentChatterInvited::dispatch($agent, $email, $invitation->token);

        return new AgentChatterInvitationData(
            email: $email,
            token: $invitation->token,
            expires_at: $invitation->expires_at,
        );
    }

    private function derivedNameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');
        $clean = Str::trim(Str::replace(['.', '_', '-', '+'], ' ', $local));

        return Str::headline($clean) ?: 'Member';
    }
}
