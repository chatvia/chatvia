<?php

namespace App\Identity\Chatter\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Admin edit of a chatter's access controls from the Members detail sheet:
 * the optional monthly token budget (null = no limit) and the deactivation
 * switch with an optional reason.
 */
class UpdateChatterAccessData extends Data
{
    public function __construct(
        public User $chatter,
        public ?int $tokensLimit,
        public bool $deactivated,
        public ?string $deactivationReason = null,
    ) {}
}
