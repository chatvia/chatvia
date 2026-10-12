<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Models\Chatter;
use Spatie\LaravelData\Data;

/**
 * Admin edit of a chatter's access controls from the Members detail sheet:
 * the optional monthly message cap (null = no limit) and the deactivation
 * switch with an optional reason.
 */
class UpdateChatterAccessData extends Data
{
    public function __construct(
        public Chatter $chatter,
        public ?int $messagesLimit,
        public bool $deactivated,
        public ?string $deactivationReason = null,
    ) {}
}
