<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Events;

use App\Identity\Workspace\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace admin asked to send a chatter the standard password-reset link.
 */
class ChatterPasswordResetRequested
{
    use Dispatchable;

    public function __construct(
        public readonly User $chatter,
    ) {}
}
