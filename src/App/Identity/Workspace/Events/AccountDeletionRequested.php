<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a workspace owner confirms their password to delete their
 * account. Carries a temporary signed confirmation URL (valid 30 minutes) plus
 * the recipient's email and name. A listener (SendAccountDeletionConfirmationEmail)
 * consumes this and routes the mail — the event stays HTTP-agnostic.
 */
class AccountDeletionRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $email,
        public string $url,
        public string $name,
    ) {}
}
