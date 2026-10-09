<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Data;

use Domain\Membership\Team\Models\Invitation;
use Spatie\LaravelData\Data;

/**
 * Input for accepting a team invitation: the invitation from the signed link,
 * plus the password (existing account) or name and password (new account).
 */
class AcceptInvitationData extends Data
{
    public function __construct(
        public Invitation $invitation,
        public ?string $password = null,
        public ?string $name = null,
    ) {}
}
