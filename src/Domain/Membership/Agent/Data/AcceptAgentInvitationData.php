<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Data;

use App\Identity\Workspace\Enums\SocialProvider;
use Domain\Membership\Agent\Actions\AcceptAgentInvitationAction;
use Domain\Membership\Agent\Enums\InvitationCredentialKind;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Spatie\LaravelData\Data;

/**
 * Input for {@see AcceptAgentInvitationAction::execute}. `invitation` is the
 * row the caller looked up from the link's token; the Action re-reads it under
 * a lock, checks it is still valid and deletes it in the same transaction.
 *
 * `kind` discriminates the path:
 *  - Password: $password is set, social fields are null
 *  - Social  : $socialEmail/$socialProvider/$socialProviderId are set,
 *              $password is null
 *
 * The Action's hash_equals guard verifies that $socialEmail matches the
 * invited email on the Social path — defends against forwarded invite links
 * being redirected onto a different account.
 */
class AcceptAgentInvitationData extends Data
{
    public function __construct(
        public AgentChatterInvitation $invitation,
        public string $name,
        public InvitationCredentialKind $kind,
        public ?string $password = null,
        public ?string $socialEmail = null,
        public ?SocialProvider $socialProvider = null,
        public ?string $socialProviderId = null,
    ) {}
}
