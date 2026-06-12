<?php

namespace Domain\Membership\Agent\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Agent\Data\AgentMemberInvitationData;
use Domain\Membership\Agent\Data\InviteAgentMemberData;
use Domain\Membership\Agent\Data\InviteWorkspaceMemberData;
use Illuminate\Support\Facades\DB;
use Support\Enums\User\UserType;

/**
 * Invite a chatter from the workspace Members page and grant them access to a
 * set of protected agents in one step.
 *
 * Reuses {@see InviteAgentMemberAction} for the first agent — which find-or-
 * creates the chatter, attaches the grant, and mints + emails the invitation —
 * then {@see GrantAgentMembershipAction} for the rest, so the invite and grant
 * semantics stay identical to the per-agent Members tab. At least one agent is
 * required (enforced by the FormRequest): a chatter with no agent access could
 * not chat with anything.
 *
 * @throws \Domain\Membership\Agent\Exceptions\AgentMemberInvitationRejectedException
 */
class InviteWorkspaceMemberAction
{
    public function __construct(
        private InviteAgentMemberAction $inviteToAgent,
        private GrantAgentMembershipAction $grant,
    ) {}

    public function execute(InviteWorkspaceMemberData $data): AgentMemberInvitationData
    {
        $agents = $data->agents;

        return DB::transaction(function () use ($data, $agents): AgentMemberInvitationData {
            // The first agent creates (or finds) the chatter, attaches the grant,
            // and mints + emails the invitation link.
            $invitation = $this->inviteToAgent->execute(
                new InviteAgentMemberData($agents->first(), $data->email),
            );

            // Grant the remaining selected agents to the now-provisioned chatter.
            $rest = $agents->slice(1);

            if ($rest->isNotEmpty()) {
                /** @var User $chatter */
                $chatter = User::query()
                    ->where('email', $data->email)
                    ->where('type', UserType::Chatter)
                    ->firstOrFail();

                foreach ($rest as $agent) {
                    $this->grant->execute($agent, $chatter);
                }
            }

            return $invitation;
        });
    }
}
