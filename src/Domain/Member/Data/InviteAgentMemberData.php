<?php

namespace Domain\Member\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for `InviteAgentMemberAction::execute()` — provision a chatter for an
 * agent. `email` arrives already lowercased + trimmed from the FormRequest.
 */
class InviteAgentMemberData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $email,
    ) {}
}
