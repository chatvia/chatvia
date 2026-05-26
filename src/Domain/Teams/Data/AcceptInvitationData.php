<?php

namespace Domain\Teams\Data;

use App\Teams\Models\Invitation;
use Spatie\LaravelData\Data;

class AcceptInvitationData extends Data
{
    public function __construct(
        public Invitation $invitation,
        public ?string $password = null,
        public ?string $name = null,
    ) {}
}
