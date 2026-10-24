<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Chatter\Models\Chatter;
use Domain\Membership\Agent\Data\AgentChatterInvitationData;
use Domain\Membership\Agent\Data\InviteAgentChatterData;
use Domain\Membership\Agent\Data\InviteWorkspaceChatterData;
use Domain\Membership\Agent\Exceptions\AgentChatterInvitationRejectedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invite a chatter from the workspace Members page and grant them access to a
 * set of protected agents in one step.
 *
 * Reuses {@see InviteAgentChatterAction} for the first agent — which find-or-
 * creates the chatter, attaches the grant, and mints + emails the invitation —
 * then {@see GrantAgentMembershipAction} for the rest, so the invite and grant
 * semantics stay identical to the per-agent Members tab. At least one agent is
 * required (enforced by the FormRequest): a chatter with no agent access could
 * not chat with anything.
 *
 * @throws AgentChatterInvitationRejectedException
 */
class InviteWorkspaceChatterAction
{
    public function __construct(
        private readonly InviteAgentChatterAction $inviteToAgent,
        private readonly GrantAgentMembershipAction $grant,
    ) {}

    public function execute(InviteWorkspaceChatterData $data): AgentChatterInvitationData
    {
        $agents = $data->agents;

        return DB::transaction(function () use ($data, $agents): AgentChatterInvitationData {
            // The first agent creates (or finds) the chatter, attaches the grant,
            // and mints + emails the invitation link.
            $invitation = $this->inviteToAgent->execute(
                new InviteAgentChatterData($agents->first(), $data->email),
            );

            // Grant the remaining selected agents to the now-provisioned chatter.
            $rest = $agents->slice(1);

            if ($rest->isNotEmpty()) {
                /** @var Chatter $chatter */
                $chatter = Chatter::query()
                    ->where('workspace_id', $agents->first()->workspace_id)
                    ->whereRaw('lower(email) = ?', [Str::lower(Str::trim($data->email))])
                    ->firstOrFail();

                foreach ($rest as $agent) {
                    $this->grant->execute($agent, $chatter);
                }
            }

            return $invitation;
        });
    }
}
