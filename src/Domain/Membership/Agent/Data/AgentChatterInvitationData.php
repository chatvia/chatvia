<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Payload returned after creating an agent-chatter invitation: the invitation
 * token (the HTTP layer turns it into the accept link the dashboard lets the
 * owner copy/share) and its expiry.
 */
class AgentChatterInvitationData extends Data
{
    public function __construct(
        public string $email,
        public string $token,
        public Carbon $expires_at,
    ) {}
}
