<?php

namespace Domain\Membership\Agent\Data;

use Domain\Agent\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for `InviteAgentChatterAction::execute()` — provision a chatter for an
 * agent. `email` arrives already lowercased + trimmed from the FormRequest.
 */
class InviteAgentChatterData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $email,
    ) {}
}
