<?php

namespace Domain\Team\Data;

use App\Team\Models\Invitation;
use Spatie\LaravelData\Data;

class AcceptInvitationData extends Data
{
    public function __construct(
        public Invitation $invitation,
        public ?string $password = null,
        public ?string $name = null,
    ) {}
}
