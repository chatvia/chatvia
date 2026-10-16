<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Events;

use App\Identity\Chatter\Enums\ChatterBlockNotice;
use App\Identity\Chatter\Models\Chatter;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace admin blocked a chatter for abuse: their account is switched
 * off, and they may be told by email.
 */
class ChatterBlocked
{
    use Dispatchable;

    public function __construct(
        public readonly Chatter $chatter,
        public readonly ChatterBlockNotice $notice,
    ) {}
}
