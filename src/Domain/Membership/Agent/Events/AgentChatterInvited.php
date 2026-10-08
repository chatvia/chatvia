<?php

namespace Domain\Membership\Agent\Events;

use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a chatter invitation row has been created. The recipient has not
 * been emailed yet — the queued SendAgentChatterInvitationEmail listener does
 * that once the surrounding transaction commits. Carries the agent, recipient
 * email and invitation token so the listener doesn't have to re-derive them.
 */
class AgentChatterInvited
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Agent $agent,
        public string $email,
        public string $token,
    ) {}
}
