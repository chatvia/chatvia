<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Models\Chatter;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Admin edit of a chatter's access controls from the Members detail sheet or
 * the public API: the monthly message cap (null = no limit) and the
 * deactivation switch with a reason. A field left out of a partial update
 * arrives as `Optional` and keeps its current value.
 */
class UpdateChatterAccessData extends Data
{
    public function __construct(
        public Chatter $chatter,
        public int|Optional|null $messagesLimit,
        public bool|Optional $deactivated,
        public string|Optional|null $deactivationReason = null,
    ) {}
}
