<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Events;

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace admin asked to send a chatter the standard password-reset link.
 */
class ChatterPasswordResetRequested
{
    use Dispatchable;

    public function __construct(
        public readonly Chatter $chatter,
        // The chat whose reset page the link opens; null picks one of the
        // chatter's agents.
        public readonly ?Agent $agent = null,
    ) {}
}
