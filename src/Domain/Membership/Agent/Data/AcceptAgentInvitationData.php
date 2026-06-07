<?php

namespace Domain\Membership\Agent\Data;

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Spatie\LaravelData\Data;
use Support\Enums\User\SocialProvider;

/**
 * Input for {@see AcceptAgentInvitationAction::execute}.
 *
 * `kind` discriminates the path:
 *  - Password: $password is set, social fields are null
 *  - Social  : $socialEmail/$socialProvider/$socialProviderId are set,
 *              $password is null
 *
 * The Action's hash_equals guard verifies that $socialEmail matches $invitedEmail
 * on the Social path — defends against forwarded invite links being redirected
 * onto a different account.
 */
class AcceptAgentInvitationData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $invitedEmail,
        public string $name,
        public InvitationCredentialKind $kind,
        public ?string $password = null,
        public ?string $socialEmail = null,
        public ?SocialProvider $socialProvider = null,
        public ?string $socialProviderId = null,
    ) {}
}
