<?php

namespace App\Identity\Chatter\Exceptions;

use App\Identity\Chatter\Actions\CreateChatterAction;
use DomainException;

/**
 * Thrown by {@see CreateChatterAction} when the workspace has used every seat
 * of its `workspace_users_limit` — the one limit shared by teammates and
 * chatters (plus any Members add-on). bootstrap/app.php answers it with 402
 * Payment Required, so API clients can show an upgrade prompt.
 */
class ChatterQuotaExceededException extends DomainException
{
    public function __construct(
        string $message,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function limitReached(int $limit): self
    {
        return new self(
            __("You've reached your workspace user limit (:limit), shared between team members and chatters. Upgrade your plan to add more.", ['limit' => $limit]),
            $limit,
        );
    }
}
