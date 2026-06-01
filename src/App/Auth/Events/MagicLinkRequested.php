<?php

namespace App\Auth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a magic-link URL has been minted for a given email. The recipient
 * has not been emailed yet — a listener (SendMagicLinkEmail) consumes this and
 * routes the mail. Carries the URL, the destination email, and the optional
 * context name (e.g. agent name) so the listener stays HTTP-agnostic.
 */
class MagicLinkRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $email,
        public string $url,
        public ?string $contextName = null,
    ) {}
}
