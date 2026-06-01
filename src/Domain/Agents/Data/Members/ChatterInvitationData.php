<?php

namespace Domain\Agents\Data\Members;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

/**
 * Payload returned after creating an agent-member invitation: the signed
 * accept URL (so the dashboard can copy/share it) and its expiry.
 */
class ChatterInvitationData extends Data
{
    public function __construct(
        public string $email,
        public string $accept_url,
        public Carbon $expires_at,
    ) {}
}
