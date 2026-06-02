<?php

namespace Domain\Member\Events;

use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a chatter invitation row has been created. The recipient email
 * has not been notified yet — a listener (SendAgentMemberInvitationEmail) consumes
 * this and routes the mail. Carries the agent + acceptance URL + recipient
 * email so the listener doesn't have to re-derive any of it.
 */
class AgentMemberInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Agent $agent,
        public string $email,
        public string $acceptUrl,
    ) {}
}
